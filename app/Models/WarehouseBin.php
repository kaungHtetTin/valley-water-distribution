<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseBin extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'warehouse_id', 'warehouse_zone_id', 'code', 'label', 'bin_type', 'capacity_units', 'sort_order', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['capacity_units' => 'decimal:4'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'warehouse_zone_id');
    }
}
