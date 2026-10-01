<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\AppAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! AppAccess::isActiveAccount($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('This account is inactive.', [], 403);
            }
        }

        return $next($request);
    }
}
