<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\AccessControlService;
use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccessControlController extends Controller
{
    public function __construct(private readonly AccessControlService $access, private readonly OrganizationContext $organization) {}

    public function index(): JsonResponse
    {
        $data = $this->access->dashboard($this->organization->id());

        return response()->json(['data' => ['roles' => $data['roles']->map($this->roleData(...)), 'assignments' => $data['assignments']->map($this->assignmentData(...)), 'users' => $data['users'], 'branches' => $data['branches'], 'permissions' => $data['permissions']]]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        return $this->roleResponse($request, null, 201);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $values = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180', 'unique:users,email'], 'password' => ['required', 'string', 'min:12', 'max:255']]);
        $user = $this->access->createUser($this->organization->id(), $values, $this->context($request));

        return response()->json(['data' => ['id' => $user->public_id, 'name' => $user->name, 'email' => $user->email]], 201);
    }

    public function updateRole(Request $request, string $role): JsonResponse
    {
        return $this->roleResponse($request, $role);
    }

    public function archiveRole(Request $request, string $role): JsonResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        try {
            return response()->json(['data' => $this->roleData($this->access->archiveRole($this->organization->id(), $role, $values['version'], $values['reason'], $this->context($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    public function assign(Request $request): JsonResponse
    {
        $organizationId = $this->organization->id();
        $values = $request->validate([
            'user_public_id' => ['required', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
            'role_public_id' => ['required', Rule::exists('roles', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'data_scope' => ['required', 'in:organization,branches'], 'branch_public_ids' => ['nullable', 'array'], 'branch_public_ids.*' => ['string', 'size:26'],
        ]);

        return response()->json(['data' => $this->assignmentData($this->access->assign($organizationId, $values, $this->context($request)))], 201);
    }

    public function revoke(Request $request, string $assignment): JsonResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        try {
            return response()->json(['data' => $this->assignmentData($this->access->revoke($this->organization->id(), $assignment, $values['version'], $values['reason'], $this->context($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    private function roleResponse(Request $request, ?string $publicId, int $status = 200): JsonResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $values = $request->validate(['code' => ['required', 'regex:/^[A-Z0-9_-]+$/', 'max:40'], 'name_en' => ['required', 'string', 'max:120'], 'name_my' => ['nullable', 'string', 'max:120'], 'permissions' => ['required', 'array', 'min:1'], 'permissions.*' => [Rule::in(Role::PERMISSIONS)], 'approval_limit_minor' => ['nullable', 'integer', 'min:0'], 'status' => ['required', 'in:active,inactive'], 'version' => [Rule::requiredIf($publicId !== null), 'nullable', 'integer', 'min:1']]);
        try {
            return response()->json(['data' => $this->roleData($this->access->saveRole($this->organization->id(), $publicId, $values, $this->context($request)))], $status);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    private function roleData(Role $role): array
    {
        return ['id' => $role->public_id, 'code' => $role->code, 'name' => ['en' => $role->name_en, 'my-MM' => $role->name_my], 'permissions' => $role->permissions, 'approval_limit_minor' => $role->approval_limit_minor, 'assignments_count' => $role->assignments_count ?? 0, 'status' => $role->status, 'version' => $role->lock_version];
    }

    private function assignmentData(UserRoleAssignment $assignment): array
    {
        $branchPublicIds = Branch::query()->where('organization_id', $this->organization->id())->whereIn('id', $assignment->branch_ids ?? [])->pluck('public_id')->all();

        return ['id' => $assignment->public_id, 'user' => ['id' => $assignment->user->public_id, 'name' => $assignment->user->name, 'email' => $assignment->user->email], 'role' => ['id' => $assignment->role->public_id, 'code' => $assignment->role->code, 'name' => ['en' => $assignment->role->name_en, 'my-MM' => $assignment->role->name_my]], 'data_scope' => $assignment->data_scope, 'branch_public_ids' => $branchPublicIds, 'status' => $assignment->status, 'version' => $assignment->lock_version];
    }

    private function context(Request $request): array
    {
        return ['actor_user_id' => $request->user()?->id, 'correlation_id' => $request->attributes->get('correlation_id'), 'ip_address' => $request->ip()];
    }

    private function conflict(MasterDataConflictException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage(), 'code' => $exception->conflictCode], 409);
    }
}
