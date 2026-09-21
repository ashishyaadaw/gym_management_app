<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembershipPlanController extends Controller
{
    /**
     * Active plans for everyone (with their sale status and spots left); admins can also
     * list inactive ones with ?include_inactive=1 and see the raw usage numbers and limits.
     */
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();

        $query = MembershipPlan::withUsage();
        if (! ($isAdmin && $request->boolean('include_inactive'))) {
            $query->where('is_active', true);
        }

        $plans = $query->orderByDesc('is_active')->orderBy('price')->get()
            ->each(fn (MembershipPlan $p) => $p->append(['remaining', 'sale_status']));

        // How many people hold a plan (and the limits set on it) is the gym's business; members only see spots left.
        if (! $isAdmin) {
            $plans->each(fn (MembershipPlan $p) => $p->makeHidden(['used_count', 'usage_limit', 'per_member_limit']));
        }

        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $plan = MembershipPlan::create($request->validate($this->rules()));

        return response()->json($this->withUsage($plan), 201);
    }

    public function show(MembershipPlan $membershipPlan)
    {
        return response()->json($this->withUsage($membershipPlan));
    }

    /** Edit a plan. Existing members keep what they bought; new price/limits apply to future sign-ups. */
    public function update(Request $request, MembershipPlan $membershipPlan)
    {
        $data = $request->validate($this->rules(partial: true));

        // Lowering the cap below the people already on the plan would make the numbers nonsense.
        // To stop new sign-ups on a well-subscribed plan, deactivate it instead.
        if (isset($data['usage_limit']) && $data['usage_limit'] < $membershipPlan->used) {
            throw ValidationException::withMessages([
                'usage_limit' => ["{$membershipPlan->used} people are already on this plan — the limit can't be lower than that. Deactivate the plan to stop new sign-ups."],
            ]);
        }

        $membershipPlan->update($data);

        return response()->json($this->withUsage($membershipPlan));
    }

    public function destroy(MembershipPlan $membershipPlan)
    {
        $membershipPlan->update(['is_active' => false]);

        return response()->json(['message' => 'Plan deactivated.']);
    }

    private function withUsage(MembershipPlan $plan): MembershipPlan
    {
        return MembershipPlan::withUsage()->findOrFail($plan->id)->append(['remaining', 'sale_status']);
    }

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => "$req|string|max:255",
            'description' => 'nullable|string',
            'billing_cycle' => "$req|in:monthly,yearly,pay_per_class",
            'price' => "$req|numeric|min:0",
            'class_credits' => 'nullable|integer|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            // Limits on plan use — null/blank = no limit
            'usage_limit' => 'nullable|integer|min:1',
            'per_member_limit' => 'nullable|integer|min:1',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after_or_equal:available_from',
        ];
    }
}
