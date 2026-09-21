<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'member_plan_id', 'invoice_number', 'amount', 'method',
        'status', 'due_date', 'paid_at', 'transaction_reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function memberPlan()
    {
        return $this->belongsTo(MemberPlan::class);
    }

    protected static function booted()
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->invoice_number)) {
                $payment->invoice_number = 'INV-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }
}
