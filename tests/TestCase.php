<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Tests;

use Simtabi\Laranail\Console\Providers\ConsoleServiceProvider;
use Simtabi\Laranail\Installer\Headless\Providers\InstallerServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

abstract class TestCase extends IsolatedTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            // Console provides the ProgressReporter binding the install command uses.
            ConsoleServiceProvider::class,
            InstallerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }
}
