<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\MigrationRunner;

/**
 * Runs database migrations and the optional configured seeder.
 */
class MigrateStep extends AbstractStep
{
    protected string $key = 'migrate';

    protected int $defaultPriority = 40;

    public function __construct(private readonly MigrationRunner $runner) {}

    public function run(InstallerContext $context): void
    {
        $seeder = config('installer.database.seeder');

        $this->runner->run(is_string($seeder) && $seeder !== '' ? $seeder : null);
    }
}
