<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleAssignment extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'user_id', 'role_id', 'data_scope', 'branch_ids', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['branch_ids' => 'array'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
