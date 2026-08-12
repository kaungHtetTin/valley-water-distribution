<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'brand_id', 'product_category_id', 'code', 'name_en', 'name_my', 'description', 'active_from', 'active_to', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['active_from' => 'date', 'active_to' => 'date'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }
}
