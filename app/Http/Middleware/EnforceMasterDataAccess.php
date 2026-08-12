<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMasterDataAccess
{
    public function __construct(private readonly RequireMasterDataPermission $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $permission = match (true) {
            $request->isMethodSafe() && str_ends_with($path, '/export') => 'master_data.export',
            $request->isMethodSafe() => 'master_data.view',
            str_contains($path, '/imports/') => 'master_data.import',
            str_contains($path, '/access-controls/') => 'master_data.access.manage',
            str_ends_with($path, '/approve') && str_contains($path, '/costs/') => 'master_data.costs.approve',
            str_ends_with($path, '/approve') => 'master_data.prices.approve',
            default => 'master_data.manage',
        };

        return $this->permissions->handle($request, $next, $permission);
    }
}
