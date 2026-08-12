<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceBookAssignment extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'price_book_id', 'target_type', 'target_key', 'priority', 'effective_from', 'effective_to', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }
}
