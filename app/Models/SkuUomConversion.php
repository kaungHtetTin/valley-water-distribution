<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuUomConversion extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'sku_id', 'uom_id', 'factor_to_base', 'version', 'is_selling_unit', 'is_kpi_base', 'effective_from', 'effective_to', 'status'];

    protected function casts(): array
    {
        return ['factor_to_base' => 'decimal:6', 'is_selling_unit' => 'boolean', 'is_kpi_base' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
