<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceType extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'code', 'name_en', 'name_my', 'precedence', 'requires_approval', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean'];
    }

    public function priceBooks(): HasMany
    {
        return $this->hasMany(PriceBook::class);
    }
}
