<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'original_order_item_id',
        'product_id',
        'product_sku',
        'product_name',
        'unit',
        'item_type',
        'return_condition',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
