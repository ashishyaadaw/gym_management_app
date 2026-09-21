<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /** All coupons with how often each has been used (admin). */
    public function index()
    {
        return response()->json(
            Coupon::with('plan:id,name')->withCount('redemptions as redeemed_count')
                ->latest('id')->get()
                ->each(fn (Coupon $c) => $c->append('status'))
        );
    }

    public function store(Request $request)
    {
        $coupon = Coupon::create($this->validated($request));

        return response()->json($this->fresh($coupon), 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validated($request, $coupon);
        $redeemed = $coupon->redemptions()->count();

        // Redemptions are recorded against the code and the terms it was used under.
        if ($redeemed > 0 && $data['code'] !== $coupon->code) {
            throw ValidationException::withMessages(['code' => ['This coupon has been used, so its code can no longer be changed.']]);
        }
        if ($data['max_redemptions'] !== null && $data['max_redemptions'] < $redeemed) {
            throw ValidationException::withMessages(['max_redemptions' => ["It has already been redeemed {$redeemed} times."]]);
        }

        $coupon->update($data);

        return response()->json($this->fresh($coupon));
    }

    /**
     * "What would this cost with this coupon?" — used by the enrolment forms before submitting.
     * Anyone signed in can ask (members for themselves); rate-limited to make guessing codes impractical.
     */
    public function check(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'membership_plan_id' => 'required|exists:membership_plans,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $actor = $request->user();
        $member = $actor->isMember() ? $actor : (isset($data['user_id']) ? User::find($data['user_id']) : null);
        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);

        $quote = $this->billing->quote($plan, $data['code'], $member);

        return response()->json([
            'code' => $quote['coupon']->code,
            'description' => $quote['coupon']->description,
            'price' => (float) $plan->price,
            'discount' => $quote['discount'],
            'final_price' => $quote['price'],
        ]);
    }

    private function fresh(Coupon $coupon): Coupon
    {
        return Coupon::with('plan:id,name')->withCount('redemptions as redeemed_count')->findOrFail($coupon->id)->append('status');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        // Codes are case-insensitive; normalise before the unique check.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'membership_plan_id' => 'nullable|exists:membership_plans,id',
            'max_redemptions' => 'nullable|integer|min:1',
            'once_per_member' => 'sometimes|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'sometimes|boolean',
        ], ['code.regex' => 'Use letters, numbers, dashes and underscores only (no spaces).']);

        if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => ['A percentage discount cannot be more than 100.']]);
        }

        return $data;
    }
}
