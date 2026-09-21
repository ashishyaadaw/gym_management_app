<?php

namespace App\Services;

use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Staff clock-in/out, admin corrections, and the monthly grid.
 *
 * The rule for what a day *is* lives in dayState(); payroll uses the same method so the attendance
 * screen and the salary can never disagree.
 */
class StaffAttendanceService
{
    /**
     * outside   – before they joined / after they left
     * future    – a working day that hasn't happened yet
     * pending   – today, not marked yet
     * unmarked  – a past working day nobody marked
     * weekly_off – their day off (paid), unless a record says otherwise
     * not_worked – an hourly person with no record: they simply had no shift that day (not a gap to fix)
     * present | half_day | absent | leave | holiday – from the record
     */
    public function dayState(StaffProfile $profile, Carbon $date, ?StaffAttendance $record): string
    {
        if (! $profile->isEmployedOn($date)) {
            return 'outside';
        }
        if ($record) {
            return $record->status;
        }
        if ($profile->isWeeklyOff($date)) {
            return 'weekly_off';
        }
        if ($date->gt(today())) {
            return 'future';
        }
        if ($date->isSameDay(today())) {
            return 'pending';
        }

        // Hourly staff only get paid for the shifts they work, so an empty day is not "forgotten".
        return $profile->isHourly() ? 'not_worked' : 'unmarked';
    }

    // ---------- Self-service clock ----------

    public function clockIn(User $user): StaffAttendance
    {
        $profile = $this->profileFor($user);
        $now = now();

        if (! $profile->isEmployedOn($now)) {
            throw ValidationException::withMessages(['clock' => ['Your employment record does not cover today.']]);
        }

        $record = StaffAttendance::where('user_id', $user->id)->whereDate('work_date', today())->first();

        if ($record && $record->check_in_at) {
            throw ValidationException::withMessages(['clock' => ['You have already clocked in today.']]);
        }
        if ($record && in_array($record->status, ['leave', 'holiday'], true)) {
            throw ValidationException::withMessages(['clock' => ['Today is marked as '.($record->status === 'leave' ? 'leave' : 'a holiday').'. Ask the admin to change it if you are working.']]);
        }

        return StaffAttendance::updateOrCreate(
            ['user_id' => $user->id, 'work_date' => today()->toDateString()],
            [
                'status' => 'present',
                'check_in_at' => $now,
                'check_out_at' => null,
                'is_late' => $this->isLate($profile, $now),
                'worked_minutes' => 0,
                'marked_by' => $user->id,
            ],
        );
    }

    public function clockOut(User $user): StaffAttendance
    {
        $this->profileFor($user);

        $record = StaffAttendance::where('user_id', $user->id)->whereDate('work_date', today())->first();

        if (! $record || ! $record->isOpen()) {
            throw ValidationException::withMessages(['clock' => ['You are not clocked in.']]);
        }

        $out = now();
        $record->update([
            'check_out_at' => $out,
            'worked_minutes' => max(0, (int) $record->check_in_at->diffInMinutes($out)),
        ]);

        return $record;
    }

    // ---------- Admin corrections ----------

    /** Create or change one person's record for one day. Times are "H:i" strings for that date. */
    public function mark(StaffProfile $profile, Carbon $date, string $status, ?string $in, ?string $out, ?string $note, ?User $by): StaffAttendance
    {
        if (! $profile->isEmployedOn($date)) {
            throw ValidationException::withMessages(['date' => [
                'Not employed on '.$date->format('d M Y').' (joined '.$profile->joining_date->format('d M Y').
                ($profile->leaving_date ? ', left '.$profile->leaving_date->format('d M Y') : '').').',
            ]]);
        }
        if ($date->gt(today()) && in_array($status, ['present', 'half_day', 'absent'], true)) {
            throw ValidationException::withMessages(['status' => ['You can only mark leave or a holiday in advance.']]);
        }

        $works = in_array($status, ['present', 'half_day'], true);
        $in = $works ? $in : null;
        $out = $works ? $out : null;

        if ($out && ! $in) {
            throw ValidationException::withMessages(['check_in' => ['Enter the clock-in time as well.']]);
        }
        if ($works && $profile->isHourly() && (! $in || ! $out)) {
            throw ValidationException::withMessages(['check_in' => ['Hourly staff need both a clock-in and a clock-out time so their pay can be worked out.']]);
        }

        $checkIn = $in ? Carbon::parse($date->toDateString().' '.$in) : null;
        $checkOut = $out ? Carbon::parse($date->toDateString().' '.$out) : null;

        if ($checkIn && $checkOut && $checkOut->lte($checkIn)) {
            throw ValidationException::withMessages(['check_out' => ['Clock-out must be after clock-in.']]);
        }

        return StaffAttendance::updateOrCreate(
            ['user_id' => $profile->user_id, 'work_date' => $date->toDateString()],
            [
                'status' => $status,
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'is_late' => $checkIn ? $this->isLate($profile, $checkIn) : false,
                'worked_minutes' => $checkIn && $checkOut ? (int) $checkIn->diffInMinutes($checkOut) : 0,
                'note' => $note,
                'marked_by' => $by?->id,
            ],
        );
    }

    /** Remove a day's record so it goes back to "not marked". */
    public function clear(StaffProfile $profile, Carbon $date): void
    {
        StaffAttendance::where('user_id', $profile->user_id)->whereDate('work_date', $date)->delete();
    }

    /**
     * Mark $status for every employed person who has nothing recorded that day (weekly offs are left alone).
     * Hourly staff are skipped for "present" because their pay needs real clock times.
     *
     * @return array{marked: int, skipped_hourly: int}
     */
    public function bulk(Carbon $date, string $status, ?User $by): array
    {
        $marked = 0;
        $skipped = 0;
        $existing = StaffAttendance::whereDate('work_date', $date)->pluck('user_id')->all();

        foreach (StaffProfile::all() as $profile) {
            if (! $profile->isEmployedOn($date) || $profile->isWeeklyOff($date) || in_array($profile->user_id, $existing, true)) {
                continue;
            }
            if ($status === 'present' && $profile->isHourly()) {
                $skipped++;

                continue;
            }
            $this->mark($profile, $date, $status, null, null, null, $by);
            $marked++;
        }

        return ['marked' => $marked, 'skipped_hourly' => $skipped];
    }

    // ---------- Reading ----------

    /**
     * The month as a grid: one row per person employed at some point in it, one cell per day.
     * Times are returned as "H:i" strings in the gym's timezone.
     */
    public function month(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $profiles = StaffProfile::with('user:id,name,role,is_active')->get()
            ->filter(fn (StaffProfile $p) => $p->user && $p->isEmployedDuring($start))
            ->sortBy(fn (StaffProfile $p) => strtolower($p->user->name))
            ->values();

        $records = StaffAttendance::whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (StaffAttendance $r) => $r->work_date->toDateString()));

        $rows = $profiles->map(function (StaffProfile $p) use ($start, $end, $records) {
            $sum = ['present' => 0, 'half_day' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'weekly_off' => 0, 'unmarked' => 0, 'late' => 0, 'minutes' => 0];
            $days = [];

            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $rec = $records->get($p->user_id)?->get($d->toDateString());
                $state = $this->dayState($p, $d, $rec);

                $days[] = [
                    'day' => $d->day,
                    'date' => $d->toDateString(),
                    'state' => $state,
                    'in' => $rec?->check_in_at?->format('H:i'),
                    'out' => $rec?->check_out_at?->format('H:i'),
                    'late' => (bool) $rec?->is_late,
                    'minutes' => (int) ($rec?->worked_minutes ?? 0),
                    'note' => $rec?->note,
                ];

                if (isset($sum[$state])) {
                    $sum[$state]++;
                }
                if ($rec) {
                    $sum['late'] += $rec->is_late ? 1 : 0;
                    $sum['minutes'] += $rec->worked_minutes;
                }
            }

            return [
                'user_id' => $p->user_id,
                'name' => $p->user->name,
                'role' => $p->user->role,
                'employee_code' => $p->employee_code,
                'pay_type' => $p->pay_type,
                'weekly_off' => $p->weekly_off,
                'shift_start' => $p->shift_start ? substr($p->shift_start, 0, 5) : null,
                'shift_end' => $p->shift_end ? substr($p->shift_end, 0, 5) : null,
                'days' => $days,
                'summary' => $sum,
            ];
        });

        return [
            'month' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'days_in_month' => $start->daysInMonth,
            'first_weekday' => $start->dayOfWeek,
            'rows' => $rows->values(),
        ];
    }

    /** Headline numbers for today (admin dashboard). */
    public function todaySummary(): array
    {
        $profiles = StaffProfile::with('user:id,name')->get()->filter(fn (StaffProfile $p) => $p->user && $p->isEmployedOn(today()));
        $records = StaffAttendance::whereDate('work_date', today())->get()->keyBy('user_id');

        $out = ['total' => $profiles->count(), 'present' => 0, 'leave' => 0, 'absent' => 0, 'weekly_off' => 0, 'pending' => 0, 'late' => 0];

        foreach ($profiles as $p) {
            $rec = $records->get($p->user_id);
            $state = $this->dayState($p, today(), $rec);

            match ($state) {
                'present', 'half_day' => $out['present']++,
                'leave', 'holiday' => $out['leave']++,
                'absent' => $out['absent']++,
                'weekly_off' => $out['weekly_off']++,
                default => $out['pending']++,
            };
            $out['late'] += $rec?->is_late ? 1 : 0;
        }

        return $out;
    }

    // ---------- Helpers ----------

    public function profileFor(User $user): StaffProfile
    {
        return $user->staffProfile ?? throw ValidationException::withMessages(['clock' => ['You do not have a staff profile yet. Ask the admin to set one up.']]);
    }

    private function isLate(StaffProfile $profile, Carbon $checkIn): bool
    {
        if (! $profile->shift_start) {
            return false;
        }

        $limit = Carbon::parse($checkIn->toDateString().' '.$profile->shift_start)->addMinutes(config('gym.late_grace_minutes'));

        return $checkIn->gt($limit);
    }
}
