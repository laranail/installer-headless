<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users;

use Override;
use Illuminate\Support\Manager;
use Spatie\Permission\PermissionRegistrar;
use Simtabi\Laranail\Installer\Headless\Contracts\RoleDriver;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\NullRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\SpatieRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\EloquentRoleDriver;

/**
 * Driver manager for role assignment. The default is `installer.user.role_driver`
 * (`spatie` | `eloquent` | `null` | a custom RoleDriver FQCN); null auto-detects
 * (Spatie if installed, else no-op). Consumers register custom drivers at runtime
 * with `RoleManager::extend('name', fn ($app) => new MyRoleDriver)` — no fork.
 */
class RoleManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $driver = config('installer.user.role_driver');

        if (is_string($driver) && $driver !== '') {
            return $driver;
        }

        return class_exists(PermissionRegistrar::class) ? 'spatie' : 'null';
    }

    /** The resolved role driver (typed accessor over {@see driver()}). */
    public function resolve(): RoleDriver
    {
        $driver = $this->driver();

        if (! $driver instanceof RoleDriver) {
            throw new InstallerException('The resolved role driver must implement ' . RoleDriver::class . '.');
        }

        return $driver;
    }

    protected function createSpatieDriver(): RoleDriver
    {
        return new SpatieRoleDriver;
    }

    protected function createEloquentDriver(): RoleDriver
    {
        return new EloquentRoleDriver;
    }

    protected function createNullDriver(): RoleDriver
    {
        return new NullRoleDriver;
    }

    /**
     * Support a custom RoleDriver FQCN as the driver name (falls back to the
     * built-in create*Driver methods / registered extensions for plain keys).
     *
     * @param string $driver
     */
    #[Override]
    protected function createDriver($driver): mixed
    {
        if (! isset($this->customCreators[$driver]) && class_exists($driver)) {
            // Check the type BEFORE building it. `is_a($class, $interface, true)` answers from the
            // class definition, so a name that is not a RoleDriver is rejected without its
            // constructor -- or any container binding it would trigger -- ever running. The driver
            // name arrives from `installer.user.role_driver`, and in a multi-tenant install that
            // value need not be something a developer hand-wrote.
            if (! is_a($driver, RoleDriver::class, true)) {
                throw new InstallerException("Configured role driver [{$driver}] must implement " . RoleDriver::class . '.');
            }

            $instance = $this->container->make($driver);

            // Still checked after construction: the container may be bound to return something else
            // for this class name entirely.
            if (! $instance instanceof RoleDriver) {
                throw new InstallerException("Configured role driver [{$driver}] must implement " . RoleDriver::class . '.');
            }

            return $instance;
        }

        return parent::createDriver($driver);
    }
}
