<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Services\PayrollService;
use App\Services\StaffAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffAttendanceController extends Controller
{
    public function __construct(private StaffAttendanceService $attendance) {}

    // ---------- Admin ----------

    /** The month grid for every staff member (?month=YYYY-MM, default this month). */
    public function month(Request $request)
    {
        return response()->json($this->attendance->month($this->requestedMonth($request)));
    }

    /** Set (or clear) one person's status for one day. */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:present,half_day,absent,leave,holiday,clear',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'note' => 'nullable|string|max:255',
        ]);

        $profile = StaffProfile::where('user_id', $data['user_id'])->first()
            ?? throw ValidationException::withMessages(['user_id' => ['This person has no staff profile yet.']]);
        $date = Carbon::parse($data['date']);

        if ($data['status'] === 'clear') {
            $this->attendance->clear($profile, $date);

            return response()->json(['cleared' => true]);
        }

        $record = $this->attendance->mark(
            $profile, $date, $data['status'], $data['check_in'] ?? null, $data['check_out'] ?? null, $data['note'] ?? null, $request->user()
        );

        return response()->json($record);
    }

    /** Mark everyone who has nothing recorded for a day: present, or a gym holiday. */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'status' => 'required|in:present,holiday',
        ]);

        return response()->json($this->attendance->bulk(Carbon::parse($data['date']), $data['status'], $request->user()));
    }

    // ---------- Self-service (any staff member) ----------

    /** My profile, today's clock state, and my month. */
    public function my(Request $request)
    {
        $user = $request->user();
        $profile = $user->staffProfile;

        if (! $profile) {
            return response()->json(['profile' => null]);
        }

        $month = $this->requestedMonth($request);
        $grid = $this->attendance->month($month);
        $row = collect($grid['rows'])->firstWhere('user_id', $user->id);

        return response()->json([
            'profile' => [
                'employee_code' => $profile->employee_code,
                'designation' => $profile->designation,
                'pay_type' => $profile->pay_type,
                'shift_start' => $profile->shift_start ? substr($profile->shift_start, 0, 5) : null,
                'shift_end' => $profile->shift_end ? substr($profile->shift_end, 0, 5) : null,
                'weekly_off' => $profile->weekly_off,
            ],
            'today' => $this->todayState($user),
            'month' => $grid['month'],
            'label' => $grid['label'],
            'first_weekday' => $grid['first_weekday'],
            'row' => $row,
        ]);
    }

    public function clockIn(Request $request)
    {
        $this->attendance->clockIn($request->user());

        return response()->json($this->todayState($request->user()), 201);
    }

    public function clockOut(Request $request)
    {
        $this->attendance->clockOut($request->user());

        return response()->json($this->todayState($request->user()));
    }

    // ---------- Helpers ----------

    private function requestedMonth(Request $request): Carbon
    {
        return PayrollService::parseMonth($request->query('month', now()->format('Y-m')));
    }

    private function todayState($user): array
    {
        $rec = StaffAttendance::where('user_id', $user->id)->whereDate('work_date', today())->first();

        return [
            'state' => $this->attendance->dayState($user->staffProfile, today(), $rec),
            'in' => $rec?->check_in_at?->format('H:i'),
            'out' => $rec?->check_out_at?->format('H:i'),
            'late' => (bool) $rec?->is_late,
            'minutes' => (int) ($rec?->worked_minutes ?? 0),
            'open' => (bool) $rec?->isOpen(),
        ];
    }
}
