<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AccessControlService
{
    public function dashboard(int $organizationId): array
    {
        return [
            'roles' => Role::query()->where('organization_id', $organizationId)->withCount(['assignments' => fn ($query) => $query->where('status', 'active')])->orderBy('code')->get(),
            'assignments' => UserRoleAssignment::query()->where('organization_id', $organizationId)->with(['user:id,public_id,name,email', 'role:id,public_id,code,name_en,name_my'])->orderBy('user_id')->get(),
            'users' => User::query()->where('organization_id', $organizationId)->orderBy('name')->get(['public_id', 'name', 'email']),
            'branches' => Branch::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'permissions' => Role::PERMISSIONS,
        ];
    }

    public function saveRole(int $organizationId, ?string $publicId, array $values, array $context): Role
    {
        return DB::transaction(function () use ($organizationId, $publicId, $values, $context): Role {
            $role = $publicId ? Role::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($role && (int) $role->lock_version !== (int) $values['version']) {
                throw new MasterDataConflictException('This role changed after it was opened.');
            }
            if (Role::query()->where('organization_id', $organizationId)->when($role, fn ($query) => $query->whereKeyNot($role->id))->where('code', $values['code'])->exists()) {
                throw new MasterDataConflictException('This role code already exists.', 'duplicate_role');
            }
            $before = $role ? $this->snapshot($role) : null;
            unset($values['version']);
            $values['permissions'] = array_values(array_unique($values['permissions']));
            if ($role) {
                $role->update([...$values, 'lock_version' => $role->lock_version + 1]);
                $verb = 'updated';
            } else {
                $role = Role::query()->create([...$values, 'organization_id' => $organizationId]);
                $verb = 'created';
            }
            $role->loadCount(['assignments' => fn ($query) => $query->where('status', 'active')]);
            $this->audit("master_data.role.{$verb}", $role, $before, $this->snapshot($role), $context);

            return $role;
        });
    }

    public function createUser(int $organizationId, array $values, array $context): User
    {
        $user = User::query()->create([...$values, 'organization_id' => $organizationId]);
        $this->audit('master_data.user.created', $user, null, Arr::except($user->toArray(), ['id', 'organization_id', 'password', 'remember_token']), $context);

        return $user;
    }

    public function archiveRole(int $organizationId, string $publicId, int $version, string $reason, array $context): Role
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $context): Role {
            $role = Role::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ((int) $role->lock_version !== $version) {
                throw new MasterDataConflictException('This role changed after it was opened.');
            }
            if ($role->assignments()->where('status', 'active')->exists()) {
                throw new MasterDataConflictException('Remove active assignments before archiving this role.', 'role_has_assignments');
            }
            $before = $this->snapshot($role);
            $role->update(['status' => 'archived', 'lock_version' => $role->lock_version + 1]);
            $context['reason'] = $reason;
            $this->audit('master_data.role.archived', $role, $before, $this->snapshot($role), $context);

            return $role->loadCount('assignments');
        });
    }

    public function assign(int $organizationId, array $values, array $context): UserRoleAssignment
    {
        return DB::transaction(function () use ($organizationId, $values, $context): UserRoleAssignment {
            $userId = User::query()->where('organization_id', $organizationId)->where('public_id', $values['user_public_id'])->value('id') ?: abort(404);
            $roleId = Role::query()->where('organization_id', $organizationId)->where('public_id', $values['role_public_id'])->where('status', 'active')->value('id') ?: abort(404);
            $branchIds = Branch::query()->where('organization_id', $organizationId)->whereIn('public_id', $values['branch_public_ids'] ?? [])->where('status', 'active')->pluck('id')->all();
            if ($values['data_scope'] === 'branches' && count($branchIds) !== count($values['branch_public_ids'] ?? [])) {
                abort(422);
            }
            $assignment = UserRoleAssignment::query()->where('organization_id', $organizationId)->where('user_id', $userId)->where('role_id', $roleId)->first();
            $before = $assignment ? $this->snapshot($assignment) : null;
            if ($assignment) {
                $assignment->update(['data_scope' => $values['data_scope'], 'branch_ids' => $branchIds ?: null, 'status' => 'active', 'lock_version' => $assignment->lock_version + 1]);
                $verb = 'updated';
            } else {
                $assignment = UserRoleAssignment::query()->create(['organization_id' => $organizationId, 'user_id' => $userId, 'role_id' => $roleId, 'data_scope' => $values['data_scope'], 'branch_ids' => $branchIds ?: null, 'status' => 'active']);
                $verb = 'created';
            }
            $assignment->load(['user:id,public_id,name,email', 'role:id,public_id,code,name_en,name_my']);
            $this->audit("master_data.role_assignment.{$verb}", $assignment, $before, $this->snapshot($assignment), $context);

            return $assignment;
        });
    }

    public function revoke(int $organizationId, string $publicId, int $version, string $reason, array $context): UserRoleAssignment
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $context): UserRoleAssignment {
            $assignment = UserRoleAssignment::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ((int) $assignment->lock_version !== $version) {
                throw new MasterDataConflictException('This assignment changed after it was opened.');
            }
            $before = $this->snapshot($assignment);
            $assignment->update(['status' => 'revoked', 'lock_version' => $assignment->lock_version + 1]);
            $context['reason'] = $reason;
            $this->audit('master_data.role_assignment.revoked', $assignment, $before, $this->snapshot($assignment), $context);

            return $assignment->load(['user:id,public_id,name,email', 'role:id,public_id,code,name_en,name_my']);
        });
    }

    private function snapshot($model): array
    {
        return Arr::except($model->fresh()->toArray(), ['id', 'organization_id', 'user_id', 'role_id']);
    }

    private function audit(string $action, $model, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create(['organization_id' => $model->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action, 'entity_type' => $model::class, 'entity_public_id' => $model->public_id, 'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null]);
    }
}
