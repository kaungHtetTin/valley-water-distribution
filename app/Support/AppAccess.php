<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AppAccess
{
    public const APPS = ['office', 'client', 'sales', 'driver'];

    public static function allowedAppsForRole(?string $role): array
    {
        return match ($role) {
            'Owner', 'Office Staff' => ['office'],
            'Customer' => ['client'],
            'Sales Representative' => ['sales'],
            'Driver' => ['driver'],
            default => [],
        };
    }

    public static function defaultAppForRole(?string $role): ?string
    {
        return self::allowedAppsForRole($role)[0] ?? null;
    }

    public static function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'locale' => $user->locale,
            'allowed_apps' => self::allowedAppsForRole($user->role),
            'default_app' => self::defaultAppForRole($user->role),
            'permissions' => self::permissionsForRole($user->role),
        ];
    }

    public static function canAccess(User $user, string $app): bool
    {
        return in_array($app, self::allowedAppsForRole($user->role), true);
    }

    public static function permissionsForRole(?string $role): array
    {
        if (! $role) {
            return [];
        }

        return DB::table('roles')
            ->join('permission_role', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('roles.name', $role)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    public static function rolePayloads(): array
    {
        return DB::table('roles')
            ->orderBy('id')
            ->get()
            ->map(fn ($role) => [
                'name' => $role->name,
                'description' => $role->description,
                'allowed_apps' => self::allowedAppsForRole($role->name),
                'permissions' => self::permissionsForRole($role->name),
            ])
            ->all();
    }
}
