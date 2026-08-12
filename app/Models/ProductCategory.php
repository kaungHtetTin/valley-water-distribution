<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'code', 'name_en', 'name_my', 'lock_version', 'status'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
