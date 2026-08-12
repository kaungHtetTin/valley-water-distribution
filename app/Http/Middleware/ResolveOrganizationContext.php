<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = null;

        if ($request->user()?->organization_id) {
            $organization = Organization::query()->find($request->user()->organization_id);
        }

        $organizationHeader = $request->header('X-Organization-ID');

        if (! $organization && is_string($organizationHeader) && $organizationHeader !== '') {
            $organization = Organization::query()
                ->where('public_id', $organizationHeader)
                ->where('status', 'active')
                ->first();
        }

        if (! $organization && (! is_string($organizationHeader) || $organizationHeader === '') && app()->environment(['local', 'testing'])) {
            $organization = Organization::query()
                ->where('code', config('platform.development_organization_code'))
                ->where('status', 'active')
                ->first();
        }

        if (! $organization) {
            return new JsonResponse([
                'message' => 'An active organization context is required.',
                'code' => 'organization_context_required',
                'correlation_id' => $request->attributes->get('correlation_id'),
            ], 403);
        }

        $this->context->set($organization->id);
        $request->attributes->set('organization', $organization);

        return $next($request);
    }
}
