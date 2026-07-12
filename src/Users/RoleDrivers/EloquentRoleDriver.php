<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users\RoleDrivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Contracts\RoleDriver;

/**
 * Assigns a role for apps that model it as a simple column on the users table
 * (or expose an `assignRole()` method). Makes no schema assumptions: if neither
 * is present it quietly does nothing.
 */
final class EloquentRoleDriver implements RoleDriver
{
    public function assign(object $user, string $role): void
    {
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);

            return;
        }

        if ($user instanceof Model && Schema::hasColumn($user->getTable(), 'role')) {
            $user->forceFill(['role' => $role])->save();
        }
    }
}
