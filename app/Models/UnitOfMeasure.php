<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use HasPublicId;

    protected $table = 'units_of_measure';

    protected $fillable = ['organization_id', 'code', 'name_en', 'name_my', 'symbol', 'dimension', 'decimal_places', 'lock_version', 'status'];

    public function baseSkus(): HasMany
    {
        return $this->hasMany(Sku::class, 'base_uom_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(SkuUomConversion::class, 'uom_id');
    }

    public function priceItems(): HasMany
    {
        return $this->hasMany(PriceBookItem::class, 'uom_id');
    }
}
