<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WayVersion extends Model
{
    use HasPublicId;

    protected $fillable = [
        'organization_id', 'way_id', 'area_id', 'default_warehouse_id', 'version', 'boundary_description', 'service_days',
        'delivery_window_start', 'delivery_window_end', 'effective_from', 'effective_to', 'change_reason', 'status',
    ];

    protected function casts(): array
    {
        return ['service_days' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function way(): BelongsTo
    {
        return $this->belongsTo(Way::class);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }
}
