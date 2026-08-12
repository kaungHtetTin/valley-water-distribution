<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashLocation extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'branch_id', 'code', 'name_en', 'name_my', 'location_type', 'currency', 'description', 'lock_version', 'status'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
