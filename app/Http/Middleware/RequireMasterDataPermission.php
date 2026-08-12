<?php

namespace App\Http\Middleware;

use App\Models\UserRoleAssignment;
use App\Support\Tenancy\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMasterDataPermission
{
    public function __construct(private readonly OrganizationContext $organization) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! config('platform.features.authentication')) {
            return $next($request);
        }
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }
        if ((int) $user->organization_id !== $this->organization->id()) {
            return response()->json(['message' => 'Organization access denied.'], 403);
        }
        $roles = UserRoleAssignment::query()->with('role')->where('organization_id', $this->organization->id())->where('user_id', $user->id)->where('status', 'active')->whereHas('role', fn ($query) => $query->where('status', 'active')->whereJsonContains('permissions', $permission))->get()->pluck('role');
        if ($roles->isEmpty()) {
            return response()->json(['message' => 'Permission denied.', 'permission' => $permission], 403);
        }
        if ($roles->every(fn ($role) => $role->approval_limit_minor !== null)) {
            $request->attributes->set('approval_limit_minor', (int) $roles->max('approval_limit_minor'));
        }

        return $next($request);
    }
}
