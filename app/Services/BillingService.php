<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\MemberPlan;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    /**
     * Subscribe a member to a plan and generate the first invoice (with an optional coupon).
     */
    public function subscribe(User $member, MembershipPlan $plan, bool $autoRenew = true, ?string $couponCode = null): MemberPlan
    {
        return DB::transaction(function () use ($member, $plan, $autoRenew, $couponCode) {
            [$plan, $quote] = $this->claim($member, $plan, $couponCode);

            $start = now();
            $end = $plan->duration_days ? $start->copy()->addDays($plan->duration_days) : null;

            $memberPlan = MemberPlan::create([
                'user_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => $start,
                'end_date' => $end,
                'remaining_credits' => $plan->class_credits,
                'status' => 'active',
                'auto_renew' => $plan->billing_cycle === 'pay_per_class' ? false : $autoRenew,
                'next_billing_date' => $plan->billing_cycle === 'pay_per_class' ? null : $end,
            ]);

            $this->redeem($quote['coupon'], $member, $memberPlan, $quote['discount']);
            $this->generateInvoice($memberPlan, null, $quote['price'], $this->couponNote($quote));

            return $memberPlan;
        });
    }

    /**
     * Front-desk enrolment / renewal: start a plan on $start, record what was
     * paid now, and raise a pending invoice for whatever is still due.
     *
     * Front-desk plans are renewed by hand, so auto-renew is switched off on the
     * new plan and on any earlier plan (otherwise the daily billing run would
     * invoice the old one again).
     */
    public function enroll(User $member, MembershipPlan $plan, Carbon $start, float $paid, string $method, ?string $couponCode = null): MemberPlan
    {
        return DB::transaction(function () use ($member, $plan, $start, $paid, $method, $couponCode) {
            [$plan, $quote] = $this->claim($member, $plan, $couponCode);

            if ($paid > $quote['price']) {
                throw ValidationException::withMessages([
                    'paid' => ['Amount paid cannot be more than the amount payable ('.config('gym.currency').number_format($quote['price'], 2).').'],
                ]);
            }

            MemberPlan::where('user_id', $member->id)->where('status', 'active')->update(['auto_renew' => false]);

            $end = $plan->duration_days ? $start->copy()->addDays($plan->duration_days) : null;

            $memberPlan = MemberPlan::create([
                'user_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => $start,
                'end_date' => $end,
                'remaining_credits' => $plan->class_credits,
                'status' => 'active',
                'auto_renew' => false,
                'next_billing_date' => null,
            ]);

            $this->redeem($quote['coupon'], $member, $memberPlan, $quote['discount']);

            if ($paid > 0) {
                Payment::create([
                    'user_id' => $member->id,
                    'member_plan_id' => $memberPlan->id,
                    'amount' => $paid,
                    'method' => $method,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'notes' => $this->couponNote($quote),
                ]);
            }

            $due = round($quote['price'] - $paid, 2);
            if ($due > 0) {
                Payment::create([
                    'user_id' => $member->id,
                    'member_plan_id' => $memberPlan->id,
                    'amount' => $due,
                    'method' => 'other',
                    'status' => 'pending',
                    'due_date' => now(),
                    'notes' => 'Balance due',
                ]);
            }

            return $memberPlan;
        });
    }

    /**
     * What a member would pay for $plan with $couponCode (no coupon = full price). Throws a
     * validation error on `coupon_code` when the coupon can't be used. Pass $lock inside a
     * transaction to hold the coupon row while its redemption is recorded.
     *
     * @return array{coupon: ?Coupon, discount: float, price: float}
     */
    public function quote(MembershipPlan $plan, ?string $couponCode, ?User $member = null, bool $lock = false): array
    {
        $price = (float) $plan->price;
        $code = trim((string) $couponCode);

        if ($code === '') {
            return ['coupon' => null, 'discount' => 0.0, 'price' => $price];
        }

        $query = Coupon::where('code', strtoupper($code));
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => ['That coupon code is not valid.']]);
        }
        if ($problem = $coupon->problemFor($plan, $member)) {
            throw ValidationException::withMessages(['coupon_code' => [$problem]]);
        }

        $discount = $coupon->discountFor($price);

        return ['coupon' => $coupon, 'discount' => $discount, 'price' => round($price - $discount, 2)];
    }

    /**
     * Take a spot on the plan: lock its row, check it is on sale and within its limits, and price it.
     * Must run inside a transaction — the lock is what stops two sign-ups taking the last spot.
     *
     * @return array{0: MembershipPlan, 1: array{coupon: ?Coupon, discount: float, price: float}}
     */
    private function claim(User $member, MembershipPlan $plan, ?string $couponCode): array
    {
        $plan = MembershipPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();

        if ($reason = $plan->unavailableReason($member)) {
            throw ValidationException::withMessages(['membership_plan_id' => [$reason]]);
        }

        return [$plan, $this->quote($plan, $couponCode, $member, lock: true)];
    }

    private function redeem(?Coupon $coupon, User $member, MemberPlan $memberPlan, float $discount): void
    {
        if ($coupon) {
            CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'user_id' => $member->id,
                'member_plan_id' => $memberPlan->id,
                'discount_amount' => $discount,
            ]);
        }
    }

    private function couponNote(array $quote): ?string
    {
        return $quote['coupon']
            ? 'Coupon '.$quote['coupon']->code.' (-'.config('gym.currency').number_format($quote['discount'], 2).')'
            : null;
    }

    /** Invoice for the plan's price, or for $amount when a coupon has changed it. */
    public function generateInvoice(MemberPlan $memberPlan, ?Carbon $dueDate = null, ?float $amount = null, ?string $notes = null): Payment
    {
        return Payment::create([
            'user_id' => $memberPlan->user_id,
            'member_plan_id' => $memberPlan->id,
            'amount' => $amount ?? $memberPlan->plan->price,
            'method' => 'other',
            'status' => 'pending',
            'due_date' => $dueDate ?? now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Cancel a member's plan; if immediate, mark cancelled now; otherwise
     * it just stops auto-renewing at the end of the current cycle.
     */
    public function cancel(MemberPlan $memberPlan, bool $immediate = false): MemberPlan
    {
        $memberPlan->auto_renew = false;

        if ($immediate) {
            $memberPlan->status = 'cancelled';
            $memberPlan->cancelled_at = now();
        }

        $memberPlan->save();

        return $memberPlan;
    }

    /**
     * Run daily: expire plans past end_date, and auto-renew plans that are
     * due for billing today (monthly/yearly with auto_renew = true).
     * Intended to be invoked from the `billing:run` scheduled command.
     */
    public function runDailyBilling(): array
    {
        $renewed = 0;
        $expired = 0;

        // Expire plans whose end_date has passed and are not set to renew.
        MemberPlan::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->where('auto_renew', false)
            ->each(function (MemberPlan $mp) use (&$expired) {
                $mp->update(['status' => 'expired']);
                $expired++;
            });

        // Renew plans due today.
        MemberPlan::where('status', 'active')
            ->where('auto_renew', true)
            ->whereDate('next_billing_date', '<=', now())
            ->with('plan')
            ->each(function (MemberPlan $mp) use (&$renewed) {
                $plan = $mp->plan;
                $newEnd = $plan->duration_days ? now()->addDays($plan->duration_days) : null;

                $mp->update([
                    'end_date' => $newEnd,
                    'next_billing_date' => $newEnd,
                    'remaining_credits' => $plan->class_credits,
                ]);

                $this->generateInvoice($mp);
                $renewed++;
            });

        return ['renewed' => $renewed, 'expired' => $expired];
    }
}
