<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClientOutlet extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'client_account_id', 'code', 'name_en', 'name_my', 'is_primary', 'status', 'lock_version'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientOutletAddress::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(ClientOutletAddress::class)->where('is_primary', true);
    }

    public function wayAssignments(): HasMany
    {
        return $this->hasMany(OutletWayAssignment::class);
    }

    public function currentWayAssignment(): HasOne
    {
        return $this->hasOne(OutletWayAssignment::class)->where('role', 'primary')->where('status', 'active')->whereNull('effective_to')->latestOfMany('effective_from');
    }
}
