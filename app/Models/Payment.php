<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'member_plan_id', 'invoice_number', 'amount', 'method',
        'status', 'due_date', 'paid_at', 'transaction_reference', 'notes', 'source', 'recorded_by',
    ];

    /** source value for payments entered on the Past Records page (the gym's history before this software). */
    public const SOURCE_HISTORY = 'history';

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

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
