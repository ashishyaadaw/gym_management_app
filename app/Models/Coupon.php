<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'membership_plan_id',
        'max_redemptions', 'once_per_member', 'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'once_per_member' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    /** Codes are case-insensitive: always stored (and looked up) upper-case. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function getRedeemedAttribute(): int
    {
        return (int) ($this->attributes['redeemed_count'] ?? $this->redemptions()->count());
    }

    /** inactive | scheduled | expired | used_up | active */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }
        if ($this->valid_from && $this->valid_from->isFuture()) {
            return 'scheduled';
        }
        if ($this->valid_until && $this->valid_until->copy()->endOfDay()->isPast()) {
            return 'expired';
        }
        if ($this->max_redemptions !== null && $this->redeemed >= $this->max_redemptions) {
            return 'used_up';
        }

        return 'active';
    }

    /** The discount this coupon gives on $price, never more than the price itself. */
    public function discountFor(float $price): float
    {
        $off = $this->discount_type === 'percent'
            ? $price * (float) $this->discount_value / 100
            : (float) $this->discount_value;

        return round(min($off, $price), 2);
    }

    /** Why this coupon can't be used for $plan by $member — or null when it can. */
    public function problemFor(MembershipPlan $plan, ?User $member = null): ?string
    {
        if ($this->membership_plan_id && $this->membership_plan_id !== $plan->id) {
            return 'This coupon is not valid for the selected plan.';
        }

        $problem = match ($this->status) {
            'inactive' => 'This coupon is not active.',
            'scheduled' => 'This coupon is not valid yet — it starts on '.$this->valid_from->format('d M Y').'.',
            'expired' => 'This coupon has expired.',
            'used_up' => 'This coupon has been fully redeemed.',
            default => null,
        };
        if ($problem) {
            return $problem;
        }

        if ($member && $this->once_per_member && $this->redemptions()->where('user_id', $member->id)->exists()) {
            return 'This coupon has already been used by this member.';
        }

        return null;
    }
}
