<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['credit_limit' => 'decimal:2', 'is_active' => 'boolean'];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function route()
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    public function priceType()
    {
        return $this->belongsTo(PriceType::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
