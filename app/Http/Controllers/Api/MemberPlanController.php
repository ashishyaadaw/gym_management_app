<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\Request;

class MemberPlanController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    /** List plan subscriptions — member sees own, staff can filter by user_id. */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = \App\Models\MemberPlan::query()->with('plan');

        if ($user->isMember()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json($query->latest()->paginate($request->get('per_page', 20)));
    }

    /** Subscribe a member to a plan (self-service or staff-assisted). */
    public function store(Request $request)
    {
        $actor = $request->user();

        $data = $request->validate([
            // Members always subscribe themselves; staff must say who the plan is for.
            'user_id' => $actor->isMember() ? 'nullable' : 'required|exists:users,id',
            'membership_plan_id' => 'required|exists:membership_plans,id',
            'auto_renew' => 'sometimes|boolean',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        $member = $actor->isMember() ? $actor : User::findOrFail($data['user_id']);
        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);

        $memberPlan = $this->billing->subscribe($member, $plan, $data['auto_renew'] ?? true, $data['coupon_code'] ?? null);

        return response()->json($memberPlan->load('plan'), 201);
    }

    public function show(Request $request, $id)
    {
        $memberPlan = $request->user()->isMember()
            ? $request->user()->memberPlans()->with('plan', 'payments')->findOrFail($id)
            : \App\Models\MemberPlan::with('plan', 'payments', 'user')->findOrFail($id);

        return response()->json($memberPlan);
    }

    public function cancel(Request $request, $id)
    {
        $memberPlan = $request->user()->isMember()
            ? $request->user()->memberPlans()->findOrFail($id)
            : \App\Models\MemberPlan::findOrFail($id);

        $memberPlan = $this->billing->cancel($memberPlan, $request->boolean('immediate'));

        return response()->json($memberPlan);
    }
}
