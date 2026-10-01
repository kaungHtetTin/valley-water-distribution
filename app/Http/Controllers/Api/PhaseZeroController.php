<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PhaseZeroController extends Controller
{
    public function show()
    {
        abort_unless(config('valley.demo_endpoints'), 404);

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
                ->where('email', 'like', '%@valley.test')
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
        abort_unless(config('valley.demo_endpoints'), 404);
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = DB::table('users')
            ->select('name', 'email', 'role', 'locale', 'password')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return ApiResponse::success(__('phase0.login_valid'), [
            'user' => collect((array) $user)->except('password'),
            'token_type' => 'demo',
        ]);
    }
}
