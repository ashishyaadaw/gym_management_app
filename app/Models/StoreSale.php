<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSale extends Model
{
    protected $fillable = [
        'sold_by', 'member_id', 'total', 'method', 'sold_at',
        'status', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $appends = ['receipt_number'];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** e.g. SK-20260919-0007 — the sale id padded, prefixed with the date it was made. */
    public function getReceiptNumberAttribute(): string
    {
        return 'SK-'.$this->sold_at->format('Ymd').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function items()
    {
        return $this->hasMany(StoreSaleItem::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
