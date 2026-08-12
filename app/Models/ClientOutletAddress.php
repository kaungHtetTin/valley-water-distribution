<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOutletAddress extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'client_outlet_id', 'area_id', 'label', 'township', 'ward_village', 'street_address', 'landmark', 'delivery_note', 'latitude', 'longitude', 'service_window_start', 'service_window_end', 'is_primary', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_primary' => 'boolean'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(ClientOutlet::class, 'client_outlet_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
