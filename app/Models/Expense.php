<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money paid out by the gym on a given day. Staff salaries are not recorded here — they go through Payroll. */
class Expense extends Model
{
    public const CATEGORIES = [
        'rent' => 'Rent',
        'electricity' => 'Electricity',
        'water' => 'Water',
        'internet' => 'Internet & phone',
        'equipment' => 'Equipment purchase',
        'repairs' => 'Repairs & maintenance',
        'cleaning' => 'Cleaning & housekeeping',
        'supplies' => 'Supplies & consumables',
        'store_stock' => 'Store stock purchase',
        'refreshments' => 'Tea, water & refreshments',
        'marketing' => 'Marketing & ads',
        'wages' => 'Casual wages (not payroll)',
        'transport' => 'Transport',
        'fees' => 'Taxes, licences & fees',
        'other' => 'Other',
    ];

    public const METHODS = [
        'cash' => 'Cash',
        'upi' => 'UPI',
        'card' => 'Card',
        'bank_transfer' => 'Bank transfer',
    ];

    protected $fillable = [
        'spent_on', 'category', 'description', 'amount', 'method', 'paid_to', 'reference', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date:Y-m-d',
            'amount' => 'float',
        ];
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The admin can change anything; whoever recorded an entry can fix or remove it on the day they entered it. */
    public function editableBy(User $user): bool
    {
        return $user->isAdmin() || ($this->recorded_by === $user->id && $this->created_at?->isToday());
    }
}
