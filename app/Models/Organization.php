<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasPublicId;

    protected $fillable = ['code', 'name', 'legal_name', 'registration_number', 'tax_identifier', 'phone', 'email', 'address', 'default_locale', 'document_locale', 'currency', 'inventory_valuation_method', 'timezone', 'lock_version', 'status'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function businessCalendars(): HasMany
    {
        return $this->hasMany(BusinessCalendar::class);
    }

    public function fiscalPeriods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }
}
