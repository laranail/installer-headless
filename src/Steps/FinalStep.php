<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\PostInstallCleanup;

/**
 * Finalizes installation: runs conservative post-install cleanup, sets the
 * install lock and fires InstallerFinished.
 */
class FinalStep extends AbstractStep
{
    protected string $key = 'final';

    protected int $defaultPriority = 70;

    public function __construct(
        private readonly InstallationState $state,
        private readonly PostInstallCleanup $cleanup,
    ) {}

    public function run(InstallerContext $context): void
    {
        $this->cleanup->handle();

        ($context->state() ?? $this->state)->markInstalled();

        InstallerFinished::dispatch();
    }
}
