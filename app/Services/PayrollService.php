<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a month of attendance into a payslip.
 *
 * MONTHLY pay   gross = base × payable days ÷ days in the month, where payable days =
 *               present + ½ per half day + paid leave + holidays + weekly offs.
 *               Absences — and past working days nobody marked — earn nothing. Days before joining
 *               or after leaving are excluded, so a mid-month joiner is pro-rated.
 *               Late deduction: every `late_marks_per_half_day` late arrivals cost ½ a day's pay.
 * HOURLY pay    gross = worked hours × hourly rate (from clock-in/out times).
 *
 * net = gross − late deduction + bonus − other deduction. Only a month that has ended can be paid out.
 * Someone with a base salary of 0 (the owner, a volunteer) is not on payroll: they still track attendance.
 */
class PayrollService
{
    public function __construct(private StaffAttendanceService $attendance) {}

    /** Parse "YYYY-MM" into the first day of that month. */
    public static function parseMonth(string $value): Carbon
    {
        // createFromFormat quietly rolls "2026-13" over into January 2027, so check the shape first.
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            throw ValidationException::withMessages(['month' => ['Use the format YYYY-MM.']]);
        }

        return Carbon::createFromFormat('!Y-m', $value)->startOfMonth();
    }

    public function monthHasEnded(Carbon $month): bool
    {
        return $month->copy()->endOfMonth()->toDateString() <= today()->toDateString();
    }

    /**
     * What this person earns for the month, from their attendance.
     *
     * @return array<string, mixed> payslip columns, plus `warnings` (list) and `has_unmarked` (bool)
     */
    public function compute(StaffProfile $p, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $days = $start->daysInMonth;
        $base = (float) $p->base_salary;

        $records = StaffAttendance::where('user_id', $p->user_id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn (StaffAttendance $r) => $r->work_date->toDateString());

        $n = ['present' => 0, 'half' => 0, 'leave' => 0, 'holiday' => 0, 'off' => 0, 'unmarked' => 0, 'absent' => 0];
        $late = 0;
        $minutes = 0;
        $open = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $rec = $records->get($d->toDateString());

            switch ($this->attendance->dayState($p, $d, $rec)) {
                case 'present':
                case 'half_day':
                    $rec->status === 'present' ? $n['present']++ : $n['half']++;
                    $late += $rec->is_late ? 1 : 0;
                    $minutes += $rec->worked_minutes;
                    $open += $rec->isOpen() ? 1 : 0;
                    break;
                case 'leave': $n['leave']++; break;
                case 'holiday': $n['holiday']++; break;
                case 'weekly_off': $n['off']++; break;
                case 'absent': $n['absent']++; break;
                case 'unmarked':
                case 'pending':
                    $n['unmarked']++;
                    break;
            }
        }

        $absent = $n['absent'] + $n['half'] * 0.5 + $n['unmarked'];
        $warnings = [];

        if ($p->isHourly()) {
            $payable = $n['present'] + $n['half'];
            $gross = round($minutes / 60 * $base, 2);
            $lateDeduction = 0.0;

            if ($open > 0) {
                $warnings[] = $open.' shift'.($open === 1 ? '' : 's').' with no clock-out — those hours are not counted.';
            }
        } else {
            $payable = $n['present'] + $n['half'] * 0.5 + $n['leave'] + $n['holiday'] + $n['off'];
            $gross = round($base * $payable / $days, 2);

            $per = (int) config('gym.late_marks_per_half_day');
            $lateDays = $per > 0 ? intdiv($late, $per) * 0.5 : 0;
            $lateDeduction = min($gross, round($base / $days * $lateDays, 2));

            if ($n['unmarked'] > 0) {
                $warnings[] = $n['unmarked'].' unmarked working day'.($n['unmarked'] === 1 ? '' : 's').' — counted as absent unless you mark them.';
            }
        }

        return [
            'user_id' => $p->user_id,
            'month' => $start->toDateString(),
            'pay_type' => $p->pay_type,
            'base_salary' => $base,
            'days_in_month' => $days,
            'present_days' => $n['present'],
            'half_days' => $n['half'],
            'leave_days' => $n['leave'],
            'holiday_days' => $n['holiday'],
            'weekly_off_days' => $n['off'],
            'unmarked_days' => $n['unmarked'],
            'absent_days' => $absent,
            'late_marks' => $late,
            'worked_minutes' => $minutes,
            'payable_days' => $payable,
            'gross_pay' => $gross,
            'late_deduction' => $lateDeduction,
            'warnings' => $warnings,
            'has_unmarked' => ! $p->isHourly() && $n['unmarked'] > 0,
        ];
    }

    /**
     * Create or refresh draft payslips for everyone employed in the month.
     * Paid payslips are never touched. Someone with unmarked days is skipped (and reported)
     * unless $treatUnmarkedAsAbsent is true. Bonus / deduction already entered on a draft are kept.
     *
     * @param  array<int>|null  $userIds  limit to these people
     * @return array{created: int, updated: int, skipped_paid: array, blocked: array}
     */
    public function generate(Carbon $month, ?array $userIds, bool $treatUnmarkedAsAbsent, ?User $by): array
    {
        if (! $this->monthHasEnded($month)) {
            throw ValidationException::withMessages(['month' => [
                'Salary for '.$month->format('F Y').' can be generated after the month ends ('.$month->copy()->endOfMonth()->format('d M').').',
            ]]);
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped_paid' => [], 'blocked' => []];

        $profiles = StaffProfile::with('user:id,name')->get()
            ->filter(fn (StaffProfile $p) => $p->user && $p->isOnPayroll() && $p->isEmployedDuring($month) && ($userIds === null || in_array($p->user_id, $userIds, true)));

        DB::transaction(function () use ($profiles, $month, $treatUnmarkedAsAbsent, $by, &$result) {
            foreach ($profiles as $p) {
                $existing = Payslip::where('user_id', $p->user_id)->whereDate('month', $month)->first();

                if ($existing?->isPaid()) {
                    $result['skipped_paid'][] = $p->user->name;

                    continue;
                }

                $figures = $this->compute($p, $month);

                if ($figures['has_unmarked'] && ! $treatUnmarkedAsAbsent) {
                    $result['blocked'][] = ['name' => $p->user->name, 'unmarked' => $figures['unmarked_days']];

                    continue;
                }

                $bonus = (float) ($existing->bonus ?? 0);
                $other = (float) ($existing->other_deduction ?? 0);
                $net = max(0, Payslip::netFor($figures['gross_pay'], $figures['late_deduction'], $bonus, $other));

                $data = collect($figures)->except(['warnings', 'has_unmarked', 'month'])->all();
                $data += ['bonus' => $bonus, 'other_deduction' => $other, 'net_pay' => $net, 'generated_by' => $by?->id, 'status' => 'draft'];

                if ($existing) {
                    $existing->update($data);
                    $result['updated']++;
                } else {
                    Payslip::create($data + ['month' => $month->toDateString()]);
                    $result['created']++;
                }
            }
        });

        return $result;
    }

    /** Change a draft's bonus / deduction / note and recompute the net pay. */
    public function adjust(Payslip $slip, float $bonus, float $otherDeduction, ?string $note): Payslip
    {
        if ($slip->isPaid()) {
            throw ValidationException::withMessages(['status' => ['A paid payslip cannot be changed.']]);
        }

        $net = Payslip::netFor((float) $slip->gross_pay, (float) $slip->late_deduction, $bonus, $otherDeduction);

        if ($net < 0) {
            throw ValidationException::withMessages(['other_deduction' => [
                'Deductions cannot be more than the pay earned (net would be '.config('gym.currency').number_format($net, 2).').',
            ]]);
        }

        $slip->update(['bonus' => $bonus, 'other_deduction' => $otherDeduction, 'adjustment_note' => $note, 'net_pay' => $net]);

        return $slip;
    }

    public function pay(Payslip $slip, Carbon $paidOn, string $method, ?string $reference): Payslip
    {
        if ($slip->isPaid()) {
            throw ValidationException::withMessages(['status' => ['This payslip is already paid.']]);
        }

        $slip->update([
            'status' => 'paid',
            'paid_on' => $paidOn->toDateString(),
            'payment_method' => $method,
            'payment_reference' => $reference,
        ]);

        return $slip;
    }
}
