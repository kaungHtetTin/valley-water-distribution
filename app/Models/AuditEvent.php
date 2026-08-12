<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasPublicId;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'actor_user_id',
        'action',
        'entity_type',
        'entity_public_id',
        'before_state',
        'after_state',
        'reason',
        'correlation_id',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
