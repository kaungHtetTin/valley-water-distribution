<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditApiMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) || $response->getStatusCode() >= 400) {
            return $response;
        }

        $user = $request->user();
        $payload = collect($request->except(['password', 'password_confirmation', 'current_password', 'token']))
            ->map(fn ($value) => is_string($value) ? Str::limit($value, 500) : $value)
            ->all();
        $body = json_decode($response->getContent() ?: '', true);
        $safePath = '/'.($request->route()?->uri() ?? $request->path());
        $segments = collect(explode('/', trim($safePath, '/')))->reject(fn ($segment) => $segment === 'api')->values();
        $routeId = collect($request->route()?->parameters() ?? [])->first(fn ($value) => is_numeric($value) || is_object($value));

        DB::table('audit_logs')->insert([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'app' => $this->app($request, $user?->role),
            'action' => match ($request->method()) {
                'POST' => str_contains($request->path(), 'review') || str_contains($request->path(), 'confirm') || str_contains($request->path(), 'approve') ? 'workflow' : 'create',
                'DELETE' => 'delete',
                default => 'update',
            },
            'method' => $request->method(),
            'path' => $safePath,
            'entity_type' => $segments->first(),
            'entity_id' => data_get($body, 'data.item.id') ?? data_get($body, 'data.id') ?? (is_object($routeId) ? $routeId->getKey() : $routeId),
            'status_code' => $response->getStatusCode(),
            'changes' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'created_at' => now(),
        ]);

        return $response;
    }

    private function app(Request $request, ?string $role): ?string
    {
        if (str_contains($request->path(), '/mobile/')) {
            return match ($role) {
                'Customer' => 'client',
                'Sales Representative' => 'sales',
                'Driver' => 'driver',
                default => 'mobile',
            };
        }

        return str_starts_with($request->path(), 'api/') ? 'office' : null;
    }
}
