<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'category', 'cost_price', 'selling_price', 'stock', 'is_active'];

    protected $appends = ['is_low_stock'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock <= config('gym.low_stock');
    }
}
