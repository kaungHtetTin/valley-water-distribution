<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'effective_from' => 'date', 'is_active' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function priceType()
    {
        return $this->belongsTo(PriceType::class);
    }
}
