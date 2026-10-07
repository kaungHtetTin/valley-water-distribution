<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AppAuthController extends Controller
{
    public function csrf(Request $request)
    {
        // Read the current session token; rotating it here would invalidate
        // requests in the other open Valley apps.
        return ApiResponse::success('Session token loaded.', [
            'csrf_token' => $request->session()->token(),
            'user_id' => $request->user()?->id,
        ]);
    }

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
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'app' => ['required', 'in:office,client,sales,supervisor,driver'],
        ]);

        $identifier = trim($credentials['email']);
        $identifierColumn = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $ambiguousPhone = $identifierColumn === 'phone'
            && User::query()->where('phone', $identifier)->count() !== 1;

        if ($ambiguousPhone || ! Auth::attempt([$identifierColumn => $identifier, 'password' => $credentials['password']], true)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! AppAccess::isActiveAccount($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::error(
                'This account is inactive.',
                ['email' => ['Contact an administrator to reactivate this account.']],
                403
            );
        }

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

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'shop_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $customer = $this->createCustomer([
                'shop_name' => $validated['shop_name'],
                'contact_name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
            ]);

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'role' => 'Customer',
                'locale' => 'en',
                'customer_id' => $customer->id,
                'password' => Hash::make($validated['password']),
            ]);
        });

        Auth::login($user, true);
        $request->session()->regenerate();

        return ApiResponse::success('Customer account created.', [
            'user' => AppAccess::userPayload($user),
            'redirect_to' => url('/client'),
        ], 201);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::sendResetLink(['email' => $validated['email']], function (User $user, string $token) {
            if (AppAccess::isActiveAccount($user)) {
                $user->sendPasswordResetNotification($token);
            }
        });

        return ApiResponse::success('If an active account matches that email, a password reset link has been sent.');
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if (! AppAccess::isActiveAccount($user)) {
            throw ValidationException::withMessages([
                'email' => ['This password reset link is invalid or expired.'],
            ]);
        }

        $status = Password::reset($validated, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['This password reset link is invalid or expired.'],
            ]);
        }

        if ($request->user()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success('Password reset successfully.', [
            'redirect_to' => url('/'.(AppAccess::defaultAppForRole($user->role) ?: 'office')),
        ]);
    }

    public function googleRedirect(): RedirectResponse
    {
        abort_unless($this->googleConfigured(), 503, 'Google sign in has not been configured yet.');

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return $this->googleFailure('Google sign in has not been configured yet.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $email = trim((string) $googleUser->getEmail());
            abort_if($email === '', 422, 'Google did not provide an email address.');

            $user = DB::transaction(function () use ($googleUser, $email) {
                $user = User::query()
                    ->where('google_id', $googleUser->getId())
                    ->orWhere('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($user) {
                    abort_unless($user->role === 'Customer', 403, 'This email belongs to a staff account.');
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'email_verified_at' => $user->email_verified_at ?: now(),
                    ]);

                    return $user;
                }

                $name = trim((string) $googleUser->getName()) ?: Str::before($email, '@');
                $customer = $this->createCustomer([
                    'shop_name' => $name,
                    'contact_name' => $name,
                    'phone' => '',
                    'email' => $email,
                    'address' => null,
                ]);

                return User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                    'role' => 'Customer',
                    'locale' => 'en',
                    'customer_id' => $customer->id,
                    'password' => Hash::make(Str::random(48)),
                ]);
            });

            if (! AppAccess::isActiveAccount($user)) {
                return $this->googleFailure('This account is inactive. Contact an administrator to reactivate it.');
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->to(url('/client'));
        } catch (Throwable $exception) {
            report($exception);

            return $this->googleFailure($exception->getMessage() ?: 'Google sign in could not be completed.');
        }
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
                if (File::isFile($previous)) {
                    File::delete($previous);
                }
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

    private function createCustomer(array $attributes): Customer
    {
        do {
            $code = 'CUS-'.Str::upper(Str::random(8));
        } while (Customer::withTrashed()->where('code', $code)->exists());

        return Customer::create([
            'area_id' => null,
            'route_id' => null,
            'price_type_id' => DB::table('price_types')->where('is_default', true)->where('is_active', true)->value('id'),
            'code' => $code,
            'shop_name' => $attributes['shop_name'],
            'contact_name' => $attributes['contact_name'],
            'phone' => $attributes['phone'] ?? '',
            'email' => $attributes['email'] ?? null,
            'address' => $attributes['address'] ?? null,
            'credit_limit' => 0,
            'is_active' => true,
        ]);
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function googleFailure(string $message): RedirectResponse
    {
        return redirect()->to(url('/client').'?auth_error='.rawurlencode(Str::limit($message, 180, '')));
    }
}
