<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasPublicId;

    public const PERMISSIONS = [
        'master_data.view', 'master_data.manage', 'master_data.import', 'master_data.export',
        'master_data.prices.approve', 'master_data.costs.approve', 'master_data.access.manage',
        'customers.view', 'customers.manage', 'sales.assignments.view', 'sales.assignments.manage', 'sales.assignments.publish',
    ];

    protected $fillable = ['organization_id', 'code', 'name_en', 'name_my', 'permissions', 'approval_limit_minor', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }
}
