<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSaleItem extends Model
{
    protected $fillable = ['store_sale_id', 'product_id', 'name', 'quantity', 'unit_price', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(StoreSale::class, 'store_sale_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
