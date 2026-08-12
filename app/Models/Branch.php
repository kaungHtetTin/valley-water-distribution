<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'code', 'name_en', 'name_my', 'phone', 'address', 'timezone', 'currency', 'business_day_start', 'lock_version', 'status'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function businessCalendars(): HasMany
    {
        return $this->hasMany(BusinessCalendar::class);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    public function cashLocations(): HasMany
    {
        return $this->hasMany(CashLocation::class);
    }

    public function routeTemplates(): HasMany
    {
        return $this->hasMany(RouteTemplate::class);
    }
}
