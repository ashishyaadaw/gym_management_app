<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberPlan extends Model
{
    protected $fillable = [
        'user_id', 'membership_plan_id', 'start_date', 'end_date',
        'remaining_credits', 'status', 'auto_renew', 'next_billing_date',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_billing_date' => 'date',
            'auto_renew' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }
}
