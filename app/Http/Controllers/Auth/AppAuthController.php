<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function profile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], [
            'profile_photo.max' => 'The compressed profile photo must not exceed 500 KB.',
        ]);

        if ($request->hasFile('profile_photo')) {
            $directory = config('uploads.profile_photos_path');
            File::ensureDirectoryExists($directory);
            $filename = Str::uuid().'.'.$request->file('profile_photo')->extension();
            $request->file('profile_photo')->move($directory, $filename);

            if ($user->profile_photo_path) {
                $previous = $directory.DIRECTORY_SEPARATOR.basename($user->profile_photo_path);
                if (File::isFile($previous)) File::delete($previous);
            }

            $validated['profile_photo_path'] = trim(config('uploads.profile_photos_url'), '/').'/'.$filename;
        }

        unset($validated['profile_photo']);
        $user->update($validated);

        return ApiResponse::success('Profile information updated.', [
            'user' => AppAccess::userPayload($user->fresh()),
        ]);
    }

    public function password(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return ApiResponse::success('Password updated successfully.');
    }
}
