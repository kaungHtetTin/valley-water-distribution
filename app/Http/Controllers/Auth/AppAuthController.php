<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AppAuthController extends Controller
{
    public function user(Request $request)
    {
        return ApiResponse::success('Current auth state loaded.', [
            'user' => AppAccess::userPayload($request->user()),
            'apps' => AppAccess::APPS,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'app' => ['required', 'in:office,client,sales,driver'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'), true)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! AppAccess::canAccess($user, $credentials['app'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::error(
                'This account cannot access the selected app.',
                ['app' => ['Please sign in through the correct Valley app.']],
                403
            );
        }

        return ApiResponse::success('Signed in successfully.', [
            'user' => AppAccess::userPayload($user),
            'redirect_to' => url('/'.AppAccess::defaultAppForRole($user->role)),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success('Signed out successfully.');
    }

    public function preferences(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,my'],
        ]);

        $request->user()->update($validated);

        return ApiResponse::success('Preferences saved.', [
            'user' => AppAccess::userPayload($request->user()->fresh()),
        ]);
    }
}
