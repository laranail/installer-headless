<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users\RoleDrivers;

use Simtabi\Laranail\Installer\Headless\Contracts\RoleDriver;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Assigns a role using spatie/laravel-permission, creating the role for the
 * user's guard if it does not yet exist.
 */
final class SpatieRoleDriver implements RoleDriver
{
    public function assign(object $user, string $role): void
    {
        $guard = method_exists($user, 'getDefaultGuardName')
            ? $user->getDefaultGuardName()
            : (string) config('auth.defaults.guard', 'web');

        Role::findOrCreate($role, $guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);
        }
    }
}
