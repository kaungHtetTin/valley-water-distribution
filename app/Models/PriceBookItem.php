<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceBookItem extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'price_book_id', 'sku_id', 'uom_id', 'unit_price_minor', 'minimum_quantity', 'effective_from', 'effective_to', 'approval_status', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'decimal:3', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
