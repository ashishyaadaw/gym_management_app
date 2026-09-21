<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin: the gym's staff — accounts plus employment details (joining date, pay, shift, weekly off). */
class StaffController extends Controller
{
    public function __construct(private StaffAttendanceService $attendance) {}

    /** Everyone who is not a member, with their staff profile (or null if not set up yet) and today's status. */
    public function index()
    {
        $today = StaffAttendance::whereDate('work_date', today())->get()->keyBy('user_id');

        $rows = User::where('role', '!=', 'member')->with('staffProfile')->orderBy('name')->get()
            ->map(fn (User $u) => $this->row($u, $today->get($u->id)))->values();

        return response()->json([
            'counts' => [
                'total' => $rows->count(),
                'active' => $rows->where('is_active', true)->count(),
                'no_profile' => $rows->whereNull('profile')->count(),
            ],
            'data' => $rows,
        ]);
    }

    /** Create a staff account and its employment profile together. */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'is_active' => true,
            ]);
            $this->saveProfile($user, $data);

            return $user;
        });

        return response()->json($this->row($user->fresh('staffProfile'), null), 201);
    }

    /** Edit the account and employment details. Also used to set up a profile for an existing account. */
    public function update(Request $request, User $user)
    {
        abort_if($user->isMember(), 404);

        $data = $this->validated($request, $user);
        $active = $data['is_active'] ?? $user->is_active;
        $me = $request->user();

        // Don't let the admin lock themselves (or the gym) out.
        if ($user->id === $me->id && (! $active || $data['role'] !== $user->role)) {
            throw ValidationException::withMessages(['role' => ['You cannot change your own role or deactivate your own account.']]);
        }
        $losingAdmin = $user->isAdmin() && (! $active || $data['role'] !== 'admin');
        if ($losingAdmin && ! User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['role' => ['The gym needs at least one active admin.']]);
        }

        DB::transaction(function () use ($user, $data, $active) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'is_active' => $active,
            ]);
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();

            $this->saveProfile($user, $data, $active);
        });

        $today = StaffAttendance::where('user_id', $user->id)->whereDate('work_date', today())->first();

        return response()->json($this->row($user->fresh('staffProfile'), $today));
    }

    private function saveProfile(User $user, array $data, bool $active = true): void
    {
        $profile = $user->staffProfile;

        $leaving = $data['leaving_date'] ?? null;
        if (! $active && ! $leaving) {
            $leaving = today()->toDateString();   // deactivating someone ends their employment today
        }
        if ($active && $leaving && $leaving < today()->toDateString()) {
            throw ValidationException::withMessages(['leaving_date' => ['They have already left — mark the account inactive instead.']]);
        }

        $fields = [
            'designation' => $data['designation'] ?? null,
            'joining_date' => $data['joining_date'],
            'leaving_date' => $leaving,
            'pay_type' => $data['pay_type'],
            'base_salary' => $data['base_salary'],
            'shift_start' => $data['shift_start'] ?? null,
            'shift_end' => $data['shift_end'] ?? null,
            'weekly_off' => $data['weekly_off'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($profile) {
            $profile->update($fields + (empty($data['employee_code']) ? [] : ['employee_code' => strtoupper($data['employee_code'])]));
        } else {
            $user->staffProfile()->create($fields + [
                'employee_code' => empty($data['employee_code']) ? StaffProfile::nextCode() : strtoupper($data['employee_code']),
            ]);
        }

        // Trainers keep the existing trainer profile (specialisation, bio) alongside the employment one.
        if ($data['role'] === 'trainer') {
            $user->trainerProfile()->firstOrCreate([]);
        }
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:20',
            'password' => $user ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => 'required|in:admin,trainer,receptionist',
            'is_active' => 'sometimes|boolean',
            'employee_code' => ['nullable', 'string', 'max:20', Rule::unique('staff_profiles', 'employee_code')->ignore($user?->staffProfile?->id)],
            'designation' => 'nullable|string|max:100',
            'joining_date' => 'required|date',
            'leaving_date' => 'nullable|date|after_or_equal:joining_date',
            'pay_type' => 'required|in:monthly,hourly',
            'base_salary' => 'required|numeric|min:0|max:9999999',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i',
            'weekly_off' => 'required|integer|between:0,6',
            'notes' => 'nullable|string|max:255',
        ]);

        if (! empty($data['shift_start']) && ! empty($data['shift_end']) && $data['shift_end'] <= $data['shift_start']) {
            throw ValidationException::withMessages(['shift_end' => ['The shift must end after it starts (overnight shifts are not supported).']]);
        }

        return $data;
    }

    private function row(User $u, ?StaffAttendance $todayRecord): array
    {
        $p = $u->staffProfile;

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->role,
            'is_active' => $u->is_active,
            'profile' => $p ? [
                'employee_code' => $p->employee_code,
                'designation' => $p->designation,
                'joining_date' => $p->joining_date->toDateString(),
                'leaving_date' => $p->leaving_date?->toDateString(),
                'pay_type' => $p->pay_type,
                'base_salary' => (float) $p->base_salary,
                'shift_start' => $p->shift_start ? substr($p->shift_start, 0, 5) : null,
                'shift_end' => $p->shift_end ? substr($p->shift_end, 0, 5) : null,
                'weekly_off' => $p->weekly_off,
                'notes' => $p->notes,
            ] : null,
            'today' => $p ? [
                'state' => $this->attendance->dayState($p, today(), $todayRecord),
                'in' => $todayRecord?->check_in_at?->format('H:i'),
                'late' => (bool) $todayRecord?->is_late,
            ] : null,
        ];
    }
}
