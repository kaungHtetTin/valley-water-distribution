<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sku extends Model
{
    use HasPublicId;

    protected $table = 'skus';

    protected $fillable = [
        'organization_id', 'product_id', 'base_uom_id', 'code', 'name_en', 'name_my', 'size_label', 'barcode', 'image_url',
        'volume_ml', 'weight_grams', 'shelf_life_days', 'track_lot', 'track_expiry', 'is_returnable', 'minimum_order_quantity',
        'order_step_quantity', 'minimum_delivery_quantity', 'sale_status', 'active_from', 'active_to', 'lock_version', 'status',
    ];

    protected function casts(): array
    {
        return [
            'volume_ml' => 'decimal:3', 'weight_grams' => 'decimal:3', 'minimum_order_quantity' => 'decimal:3',
            'order_step_quantity' => 'decimal:3', 'minimum_delivery_quantity' => 'decimal:3', 'track_lot' => 'boolean',
            'track_expiry' => 'boolean', 'is_returnable' => 'boolean', 'active_from' => 'date', 'active_to' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(SkuUomConversion::class);
    }

    public function priceItems(): HasMany
    {
        return $this->hasMany(PriceBookItem::class);
    }

    public function replenishmentPolicies(): HasMany
    {
        return $this->hasMany(WarehouseSkuPolicy::class);
    }
}
