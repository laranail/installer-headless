<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users\RoleDrivers;

use Simtabi\Laranail\Installer\Headless\Contracts\RoleDriver;

/**
 * No-op role driver for apps without a role system. The default when nothing
 * else is detected.
 */
final class NullRoleDriver implements RoleDriver
{
    public function assign(object $user, string $role): void
    {
        // Intentionally does nothing.
    }
}
