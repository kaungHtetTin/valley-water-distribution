<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseSkuPolicy extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'warehouse_id', 'sku_id', 'safety_stock', 'reorder_point', 'target_stock', 'replenishment_lead_days', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['safety_stock' => 'decimal:4', 'reorder_point' => 'decimal:4', 'target_stock' => 'decimal:4'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
