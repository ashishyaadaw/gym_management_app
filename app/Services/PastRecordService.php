<?php

namespace App\Services;

use App\Models\MemberPlan;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Past records: the owner types in the gym's history from before this software was installed — a year of
 * membership payments, or plain day totals from an old daybook — so reports cover the whole period.
 *
 * Unlike the front desk's enrol/renew, a past record skips the plan's sale status and limits (it already
 * happened), raises no "balance due" invoice, and marks its payment with source = 'history' so it can be undone.
 */
class PastRecordService
{
    /**
     * @param  array{paid_on: string, name?: ?string, phone?: ?string, membership_plan_id?: ?int, start_date?: ?string,
     *               amount?: ?float, method: string, reference?: ?string, notes?: ?string}  $row  already validated
     * @return array{payment: Payment, member: ?User, member_created: bool, plan: ?MemberPlan}
     */
    public function record(array $row, User $by): array
    {
        return DB::transaction(function () use ($row, $by) {
            $paidOn = Carbon::parse($row['paid_on']);
            $plan = ! empty($row['membership_plan_id']) ? MembershipPlan::findOrFail($row['membership_plan_id']) : null;
            $amount = $row['amount'] ?? null;
            if ($amount === null && $plan) {
                $amount = (float) $plan->price;
            }
            if (! $amount || $amount <= 0) {
                throw ValidationException::withMessages(['amount' => ['Enter the amount paid.']]);
            }

            [$member, $created] = $this->resolveMember($row);
            if (! $member && $plan) {
                throw ValidationException::withMessages(['name' => ['A plan needs a member — enter a name or phone.']]);
            }

            $memberPlan = null;
            if ($plan) {
                $start = ! empty($row['start_date']) ? Carbon::parse($row['start_date']) : $paidOn->copy();
                $end = $plan->duration_days ? $start->copy()->addDays($plan->duration_days) : null;
                $memberPlan = MemberPlan::create([
                    'user_id' => $member->id,
                    'membership_plan_id' => $plan->id,
                    'start_date' => $start,
                    'end_date' => $end,
                    'remaining_credits' => $plan->class_credits,
                    'status' => $end && $end->lt(today()) ? 'expired' : 'active',
                    'auto_renew' => false,
                    'next_billing_date' => null,
                ]);
            }

            // A member's "member since" is their earliest record.
            if ($member) {
                $first = $memberPlan ? $memberPlan->start_date->min($paidOn) : $paidOn;
                if (! $member->joined_on || $member->joined_on->gt($first)) {
                    $member->update(['joined_on' => $first->toDateString()]);
                }
            }

            $payment = Payment::create([
                'user_id' => $member?->id,
                'member_plan_id' => $memberPlan?->id,
                'amount' => number_format((float) $amount, 2, '.', ''), // string: the decimal cast rejects floats in future versions
                'method' => $row['method'],
                'status' => 'paid',
                // The actual time of day is unknown; noon keeps it out of the early/late edges of the time split.
                'paid_at' => $paidOn->copy()->setTime(12, 0),
                'transaction_reference' => $row['reference'] ?? null,
                'notes' => $row['notes'] ?? null,
                'source' => Payment::SOURCE_HISTORY,
                'recorded_by' => $by->id,
            ]);

            return ['payment' => $payment, 'member' => $member, 'member_created' => $created, 'plan' => $memberPlan];
        });
    }

    /**
     * Undo a past record: remove the payment, and the plan it created unless another payment is attached to it.
     * A member created by the record is kept (they may have other records, attendance, etc.).
     */
    public function undo(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $plan = $payment->memberPlan;
            $payment->delete();

            if ($plan && ! $plan->payments()->exists()) {
                $plan->delete();
            }
        });
    }

    /**
     * Existing member by phone, else by exact name (only when exactly one member has it), else a new member.
     * No name and no phone = a lump sum with no member.
     *
     * @return array{0: ?User, 1: bool} [member, was created]
     */
    private function resolveMember(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));

        if ($name === '' && $phone === '') {
            return [null, false];
        }

        if ($phone !== '') {
            $user = User::where('phone', $phone)->first();
            if ($user) {
                if (! $user->isMember()) {
                    throw ValidationException::withMessages(['phone' => ["{$phone} belongs to {$user->name}, who is staff, not a member."]]);
                }

                return [$user, false];
            }
        } else {
            $matches = User::where('role', 'member')->where('name', $name)->limit(2)->get();
            if ($matches->count() === 1) {
                return [$matches->first(), false];
            }
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['name' => ["More than one member is called {$name} — add their phone number."]]);
            }
        }

        if ($name === '') {
            throw ValidationException::withMessages(['name' => ["No member has phone {$phone} — enter a name to add them."]]);
        }

        $member = User::create([
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            // Walk-ins usually have no email; the column is required + unique, so derive one.
            'email' => ($phone !== '' ? $phone : 'past-'.Str::lower(Str::random(10))).User::DERIVED_EMAIL_DOMAIN,
            'password' => Hash::make(Str::random(16)),
            'role' => 'member',
        ]);

        return [$member, true];
    }
}
