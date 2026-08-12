<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClientAccount extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'price_book_id', 'acquiring_sales_profile_id', 'code', 'name_en', 'name_my', 'legal_name', 'searchable_alias', 'category', 'preferred_language', 'acquisition_source', 'settlement_policy', 'lifecycle_status', 'credit_hold', 'lock_version'];

    protected function casts(): array
    {
        return ['credit_hold' => 'boolean'];
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function acquiringSalesProfile(): BelongsTo
    {
        return $this->belongsTo(FoundationMasterRecord::class, 'acquiring_sales_profile_id');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(ClientOutlet::class);
    }

    public function primaryOutlet(): HasOne
    {
        return $this->hasOne(ClientOutlet::class)->where('is_primary', true);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary_ordering', true);
    }
}
