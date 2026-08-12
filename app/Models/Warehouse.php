<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'branch_id', 'area_id', 'code', 'name_en', 'name_my', 'kind', 'address', 'contact_name', 'phone', 'latitude', 'longitude', 'order_cutoff_time', 'service_area_note', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class);
    }

    public function replenishmentPolicies(): HasMany
    {
        return $this->hasMany(WarehouseSkuPolicy::class);
    }

    public function routeTemplates(): HasMany
    {
        return $this->hasMany(RouteTemplate::class, 'source_warehouse_id');
    }
}
