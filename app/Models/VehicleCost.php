<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cost_date' => 'date',
        'odometer_km' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];
}
