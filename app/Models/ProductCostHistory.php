<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCostHistory extends Model
{
    use HasPublicId;

    protected $table = 'product_cost_histories';

    protected $fillable = ['organization_id', 'warehouse_id', 'sku_id', 'unit_cost_minor', 'currency', 'valuation_method', 'effective_from', 'effective_to', 'approval_status', 'reason', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
