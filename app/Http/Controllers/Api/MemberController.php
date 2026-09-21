<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberPlan;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\MemberStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Front-desk member list, walk-in enrolment, member details and renewals. */
class MemberController extends Controller
{
    /** Walk-ins without an email get "<phone>@members.gym.local" so the required, unique column is filled. */
    private const DERIVED_EMAIL_DOMAIN = '@members.gym.local';

    public function __construct(
        private MemberStatusService $statuses,
        private BillingService $billing,
    ) {}

    /** Members with membership status. ?q= searches name/phone, ?status= filters. */
    public function index(Request $request)
    {
        $members = $this->statuses->members($request->query('q'));
        $counts = $this->statuses->counts($members);

        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $members = $members->where('status', $status);
        }

        // Soonest-to-expire first when browsing a status; otherwise newest members first.
        if (in_array($status, ['expiring', 'expired'], true)) {
            $members = $members->sortByDesc('days_left');
            $members = $status === 'expiring' ? $members->reverse() : $members;
        }

        return response()->json([
            'counts' => $counts,
            'data' => $members->values(),
        ]);
    }

    /** Register a walk-in member, start their plan and record the first payment. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^\d{10}$/', 'unique:users,phone'],
            'email' => 'nullable|email|unique:users,email',
            'joining_date' => 'nullable|date',
            'membership_plan_id' => 'required|exists:membership_plans,id',
            'paid' => 'required|numeric|min:0',
            'method' => 'required|in:cash,upi,card,bank_transfer',
            'coupon_code' => 'nullable|string|max:40',
        ] + $this->detailRules(), ['phone.regex' => 'Phone must be exactly 10 digits.']);

        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        $start = isset($data['joining_date']) ? Carbon::parse($data['joining_date']) : today();

        // One transaction: if the plan is full or the coupon is refused, the new member is not left behind.
        $member = DB::transaction(function () use ($data, $plan, $start) {
            $member = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                // Walk-ins usually have no email; the column is required + unique, so derive one.
                'email' => $data['email'] ?? $data['phone'].self::DERIVED_EMAIL_DOMAIN,
                'password' => Hash::make(Str::random(16)),
                'role' => 'member',
            ]);

            $this->saveDetails($member, $data);

            $this->billing->enroll($member, $plan, $start, (float) $data['paid'], $data['method'], $data['coupon_code'] ?? null);

            return $member;
        });

        return response()->json(['id' => $member->id], 201);
    }

    /** One member's full details: contact, emergency contact, body, fitness and health. */
    public function show(User $member)
    {
        abort_unless($member->isMember(), 404);

        $profile = $member->memberProfile;

        return response()->json([
            'id' => $member->id,
            'name' => $member->name,
            'phone' => $member->phone,
            // Walk-ins get a made-up address (see store()); only show one they actually gave.
            'email' => str_ends_with($member->email, self::DERIVED_EMAIL_DOMAIN) ? null : $member->email,
            'is_active' => $member->is_active,
            'joined_on' => $member->created_at?->toDateString(),
            'gender' => $member->gender,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'age' => $member->date_of_birth?->age,
            'address' => $member->address,
            'emergency_contact_name' => $member->emergency_contact_name,
            'emergency_contact_phone' => $member->emergency_contact_phone,
            ...collect(MemberProfile::FIELDS)->mapWithKeys(fn (string $f) => [$f => $profile?->{$f}])->all(),
            'bmi' => $profile?->bmi(),
            'bmi_category' => $profile?->bmiCategory(),
        ]);
    }

    /** Edit a member's name, phone and details. Plans, payments and login are not touched here. */
    public function update(Request $request, User $member)
    {
        abort_unless($member->isMember(), 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^\d{10}$/', Rule::unique('users', 'phone')->ignore($member->id)],
        ] + $this->detailRules(), ['phone.regex' => 'Phone must be exactly 10 digits.']);

        DB::transaction(fn () => $this->saveDetails($member, $data));

        return response()->json(['id' => $member->id]);
    }

    /** Validation for the details a member can have; every one is optional. */
    private function detailRules(): array
    {
        return [
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today|after:1900-01-01',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',
            'height_cm' => 'nullable|numeric|between:50,260',
            'weight_kg' => 'nullable|numeric|between:20,400',
            'fitness_goal' => ['nullable', Rule::in(array_keys(MemberProfile::GOALS))],
            'fitness_level' => ['nullable', Rule::in(array_keys(MemberProfile::LEVELS))],
            'blood_group' => ['nullable', Rule::in(MemberProfile::BLOOD_GROUPS)],
            'preferred_timing' => ['nullable', Rule::in(array_keys(MemberProfile::TIMINGS))],
            'referral_source' => ['nullable', Rule::in(array_keys(MemberProfile::SOURCES))],
            'occupation' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /** Save validated details: the contact fields on the user, the body/fitness/health fields on their profile. */
    private function saveDetails(User $member, array $data): void
    {
        $member->update(Arr::only($data, [
            'name', 'phone', 'gender', 'date_of_birth', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        ]));

        MemberProfile::updateOrCreate(['user_id' => $member->id], Arr::only($data, MemberProfile::FIELDS));
    }

    /** Renew: a new plan starting when the current one ends (or today if it already ended). */
    public function renew(Request $request, User $member)
    {
        abort_unless($member->isMember(), 404);

        if (! $member->is_active) {
            return response()->json(['message' => $member->name.' is deactivated. Activate the account before renewing.'], 422);
        }

        $data = $request->validate([
            'membership_plan_id' => 'required|exists:membership_plans,id',
            'paid' => 'required|numeric|min:0',
            'method' => 'required|in:cash,upi,card,bank_transfer',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);

        $currentEnd = MemberPlan::where('user_id', $member->id)
            ->where('status', 'active')->max('end_date');
        $start = $currentEnd && Carbon::parse($currentEnd)->isFuture()
            ? Carbon::parse($currentEnd)
            : today();

        $this->billing->enroll($member, $plan, $start, (float) $data['paid'], $data['method'], $data['coupon_code'] ?? null);

        return response()->json(['id' => $member->id]);
    }

    /** Switch a member's account off. Everything they have (plans, payments, attendance) is kept. */
    public function deactivate(User $member)
    {
        return $this->setActive($member, false);
    }

    /** Switch a deactivated member's account back on. */
    public function activate(User $member)
    {
        return $this->setActive($member, true);
    }

    private function setActive(User $member, bool $active)
    {
        abort_unless($member->isMember(), 404);

        $member->update(['is_active' => $active]);

        if (! $active) {
            // Sign them out everywhere: the session guard only checks is_active at login.
            DB::table('sessions')->where('user_id', $member->id)->delete();
        }

        return response()->json(['id' => $member->id, 'is_active' => $active]);
    }
}
