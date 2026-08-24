<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceType extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
