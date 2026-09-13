<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'code',
        'original_order_id',
        'return_warehouse_id',
        'customer_id',
        'area_id',
        'route_id',
        'price_type_id',
        'recipient_name',
        'recipient_phone',
        'delivery_address',
        'source_app',
        'order_date',
        'requested_delivery_date',
        'credit_due_date',
        'payment_type',
        'return_settlement_method',
        'refund_amount',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'requested_delivery_date' => 'date',
        'credit_due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function originalOrder()
    {
        return $this->belongsTo(self::class, 'original_order_id');
    }
}
