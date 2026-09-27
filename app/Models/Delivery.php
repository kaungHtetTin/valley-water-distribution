<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stop_sequence' => 'integer',
        'planned_date' => 'date',
        'total_quantity' => 'decimal:2',
        'loaded_quantity' => 'decimal:2',
        'delivered_quantity' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
        'damaged_quantity' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'order_modified' => 'boolean',
        'start_odometer_km' => 'decimal:2',
        'end_odometer_km' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'assigned_at' => 'datetime',
        'loaded_at' => 'datetime',
        'departed_at' => 'datetime',
        'completed_at' => 'datetime',
        'settled_at' => 'datetime',
        'order_modified_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function trip()
    {
        return $this->belongsTo(DeliveryTrip::class, 'trip_id');
    }

    public function locations()
    {
        return $this->hasMany(DeliveryLocation::class);
    }
}
