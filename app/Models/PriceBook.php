<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceBook extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'branch_id', 'price_type_id', 'code', 'name_en', 'name_my', 'currency', 'scope_type', 'effective_from', 'effective_to', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function priceType(): BelongsTo
    {
        return $this->belongsTo(PriceType::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceBookItem::class);
    }
}
