<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberPlan;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\MemberStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Front-desk member list, walk-in enrolment and renewals. */
class MemberController extends Controller
{
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
            'emergency_contact_phone' => 'nullable|string|max:20',
            'coupon_code' => 'nullable|string|max:40',
        ], ['phone.regex' => 'Phone must be exactly 10 digits.']);

        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        $start = isset($data['joining_date']) ? Carbon::parse($data['joining_date']) : today();

        // One transaction: if the plan is full or the coupon is refused, the new member is not left behind.
        $member = DB::transaction(function () use ($data, $plan, $start) {
            $member = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                // Walk-ins usually have no email; the column is required + unique, so derive one.
                'email' => $data['email'] ?? $data['phone'].'@members.gym.local',
                'password' => Hash::make(Str::random(16)),
                'role' => 'member',
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ]);

            $this->billing->enroll($member, $plan, $start, (float) $data['paid'], $data['method'], $data['coupon_code'] ?? null);

            return $member;
        });

        return response()->json(['id' => $member->id], 201);
    }

    /** Renew: a new plan starting when the current one ends (or today if it already ended). */
    public function renew(Request $request, User $member)
    {
        abort_unless($member->isMember(), 404);

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
}
