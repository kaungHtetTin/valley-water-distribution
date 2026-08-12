<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'branch_id', 'scope_key', 'document_type', 'name', 'prefix', 'suffix', 'padding', 'next_number', 'reset_policy', 'last_reset_period', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['next_number' => 'integer', 'padding' => 'integer'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
