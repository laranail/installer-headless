<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Contracts;

/**
 * Assigns an role to the freshly created user. Implementations
 * adapt to the host app's authorization stack (Spatie permission, a role column,
 * or no role system at all).
 */
interface RoleDriver
{
    /**
     * @param  object  $user  the created user model instance
     */
    public function assign(object $user, string $role): void;
}
