<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AppAccess
{
    public const APPS = ['office', 'client', 'sales', 'driver'];

    public static function allowedAppsForRole(?string $role): array
    {
        if (! $role) {
            return [];
        }

        $configuredRole = DB::table('roles')
            ->where('name', $role)
            ->first(['allowed_apps', 'is_active']);

        if (! $configuredRole || ! $configuredRole->is_active) {
            return [];
        }

        $apps = is_array($configuredRole->allowed_apps)
            ? $configuredRole->allowed_apps
            : json_decode($configuredRole->allowed_apps ?? '[]', true);

        return array_values(array_intersect(self::APPS, is_array($apps) ? $apps : []));
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
            ->where('roles.is_active', true)
            ->where('permissions.is_active', true)
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
