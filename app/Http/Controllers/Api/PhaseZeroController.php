<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhaseZeroController extends Controller
{
    public function show()
    {
        return ApiResponse::success(__('phase0.api_ready'), [
            'apps' => ['office', 'client', 'sales', 'driver'],
            'locales' => ['en', 'my'],
            'roles' => AppAccess::rolePayloads(),
            'permissions' => DB::table('permissions')
                ->select('group', 'name')
                ->orderBy('group')
                ->orderBy('name')
                ->get()
                ->groupBy('group'),
            'demo_users' => DB::table('users')
                ->select('name', 'email', 'phone', 'role', 'locale')
                ->orderBy('id')
                ->get(),
            'api_format' => [
                'ok' => true,
                'message' => 'Human readable status',
                'data' => [],
                'errors' => [],
            ],
        ]);
    }

    public function validateDemoLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = DB::table('users')
            ->select('name', 'email', 'role', 'locale')
            ->where('email', $validated['email'])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return ApiResponse::success(__('phase0.login_valid'), [
            'user' => $user,
            'token_type' => 'demo',
        ]);
    }
}
