<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletWayAssignment extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'client_outlet_id', 'way_id', 'effective_from', 'effective_to', 'role', 'change_reason', 'status'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(ClientOutlet::class, 'client_outlet_id');
    }

    public function way(): BelongsTo
    {
        return $this->belongsTo(Way::class);
    }
}
