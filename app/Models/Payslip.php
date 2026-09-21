<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = [
        'user_id', 'month', 'pay_type', 'base_salary', 'days_in_month',
        'present_days', 'half_days', 'leave_days', 'holiday_days', 'weekly_off_days', 'unmarked_days',
        'absent_days', 'late_marks', 'worked_minutes', 'payable_days',
        'gross_pay', 'late_deduction', 'bonus', 'other_deduction', 'adjustment_note', 'net_pay',
        'status', 'paid_on', 'payment_method', 'payment_reference', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'paid_on' => 'date:Y-m-d',
            'base_salary' => 'decimal:2',
            'absent_days' => 'float',
            'payable_days' => 'float',
            'gross_pay' => 'decimal:2',
            'late_deduction' => 'decimal:2',
            'bonus' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** SAL-202608-0003 */
    public function getSlipNumberAttribute(): string
    {
        return 'SAL-'.$this->month->format('Ym').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Net pay from the earned amount and the adjustments (never below zero). */
    public static function netFor(float $gross, float $lateDeduction, float $bonus, float $otherDeduction): float
    {
        return round($gross - $lateDeduction + $bonus - $otherDeduction, 2);
    }
}
