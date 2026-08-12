<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoundationMasterRecord extends Model
{
    use HasPublicId;

    public const TYPES = [
        'customers', 'suppliers', 'employees', 'departments', 'positions', 'cost-centers', 'drivers', 'sales-profiles',
        'vehicles', 'banks', 'gl-accounts', 'expense-types', 'damage-types', 'return-types', 'foc-types', 'failure-types',
        'maintenance-types', 'earning-types', 'deduction-types', 'incentive-types', 'allowance-types',
    ];

    protected $fillable = [
        'organization_id', 'branch_id', 'area_id', 'way_id', 'price_book_id', 'parent_id', 'type', 'code',
        'name_en', 'name_my', 'classification', 'phone', 'email', 'address', 'registration_number', 'metadata',
        'sort_order', 'lock_version', 'status',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function way(): BelongsTo
    {
        return $this->belongsTo(Way::class);
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
