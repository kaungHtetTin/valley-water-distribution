<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasPublicId;

    protected $fillable = [
        'organization_id',
        'parent_area_id',
        'code',
        'name_en',
        'name_my',
        'description',
        'sort_order',
        'lock_version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_area_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_area_id');
    }
}
