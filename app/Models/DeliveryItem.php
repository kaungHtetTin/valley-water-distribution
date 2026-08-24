<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'planned_quantity' => 'decimal:2',
        'loaded_quantity' => 'decimal:2',
        'delivered_quantity' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
        'damaged_quantity' => 'decimal:2',
    ];
}
