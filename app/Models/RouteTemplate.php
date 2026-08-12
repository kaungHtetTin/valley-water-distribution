<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RouteTemplate extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'branch_id', 'source_warehouse_id', 'code', 'name_en', 'name_my', 'description', 'service_days', 'departure_time', 'estimated_duration_minutes', 'effective_from', 'effective_to', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['service_days' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function ways(): BelongsToMany
    {
        return $this->belongsToMany(Way::class, 'route_template_ways')->withPivot('sequence')->withTimestamps()->orderByPivot('sequence');
    }
}
