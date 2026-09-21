<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    /**
     * Billing cycles, in the order they are offered, with the membership length each one starts from
     * (days; null = no fixed length, e.g. a class pack that is used up instead of expiring).
     */
    public const CYCLES = [
        'monthly' => ['label' => 'Monthly', 'days' => 30],
        'quarterly' => ['label' => 'Quarterly', 'days' => 90],
        'half_yearly' => ['label' => 'Half-yearly', 'days' => 180],
        'yearly' => ['label' => 'Yearly', 'days' => 365],
        'pay_per_class' => ['label' => 'Per class', 'days' => null],
    ];

    protected $fillable = [
        'name', 'description', 'billing_cycle', 'price',
        'class_credits', 'duration_days', 'features', 'is_active',
        'usage_limit', 'per_member_limit', 'available_from', 'available_until',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'available_from' => 'date:Y-m-d',
            'available_until' => 'date:Y-m-d',
        ];
    }

    public function memberPlans()
    {
        return $this->hasMany(MemberPlan::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Adds `used_count`: how many sign-ups count against the plan's limit (cancelled ones free their spot).
     * Lists should call this, then ->append(['remaining', 'sale_status']) on each plan, to avoid a query per plan.
     */
    public function scopeWithUsage(Builder $query): Builder
    {
        return $query->withCount(['memberPlans as used_count' => fn ($q) => $q->where('status', '!=', 'cancelled')]);
    }

    public function getUsedAttribute(): int
    {
        return (int) ($this->attributes['used_count'] ?? $this->memberPlans()->where('status', '!=', 'cancelled')->count());
    }

    /** Spots left, or null when the plan has no total limit. */
    public function getRemainingAttribute(): ?int
    {
        return $this->usage_limit === null ? null : max(0, $this->usage_limit - $this->used);
    }

    /** inactive | scheduled | ended | full | on_sale */
    public function getSaleStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }
        if ($this->available_from && $this->available_from->isFuture()) {
            return 'scheduled';
        }
        if ($this->available_until && $this->available_until->copy()->endOfDay()->isPast()) {
            return 'ended';
        }

        return $this->remaining === 0 ? 'full' : 'on_sale';
    }

    /**
     * Why this plan can't be taken right now (by $member, if given) — or null when it can.
     * Call inside a transaction holding a lock on the plan row so two sign-ups can't take the last spot.
     */
    public function unavailableReason(?User $member = null): ?string
    {
        $status = $this->sale_status;

        if ($status !== 'on_sale') {
            return match ($status) {
                'inactive' => 'This plan is not available.',
                'scheduled' => 'This plan is not on sale yet — it starts on '.$this->available_from->format('d M Y').'.',
                'ended' => 'This plan is no longer on sale.',
                'full' => 'This plan is sold out.',
            };
        }

        if ($member && $this->per_member_limit) {
            $taken = $this->memberPlans()->where('user_id', $member->id)->where('status', '!=', 'cancelled')->count();
            if ($taken >= $this->per_member_limit) {
                return 'This member has already taken this plan the maximum number of times ('.$this->per_member_limit.').';
            }
        }

        return null;
    }
}
