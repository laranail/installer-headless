<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users;

use Illuminate\Support\Manager;
use Override;
use Simtabi\Laranail\Installer\Headless\Contracts\RoleDriver;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\EloquentRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\NullRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\SpatieRoleDriver;
use Spatie\Permission\PermissionRegistrar;

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

    /** The resolved role driver (typed accessor over {@see driver()}). */
    public function resolve(): RoleDriver
    {
        $driver = $this->driver();

        if (! $driver instanceof RoleDriver) {
            throw new InstallerException('The resolved role driver must implement ' . RoleDriver::class . '.');
        }

        return $driver;
    }

    /**
     * Support a custom RoleDriver FQCN as the driver name (falls back to the
     * built-in create*Driver methods / registered extensions for plain keys).
     *
     * @param  string  $driver
     */
    #[Override]
    protected function createDriver($driver): mixed
    {
        if (! isset($this->customCreators[$driver]) && class_exists($driver)) {
            $instance = $this->container->make($driver);

            if (! $instance instanceof RoleDriver) {
                throw new InstallerException("Configured role driver [{$driver}] must implement " . RoleDriver::class . '.');
            }

            return $instance;
        }

        return parent::createDriver($driver);
    }
}
