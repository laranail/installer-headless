<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Simtabi\Laranail\Installer\Headless\Exceptions\RequirementsException;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\RequirementsChecker;

/**
 * Verifies server requirements. Stores the full report in the context (for the
 * UI/CLI to render) and fails the run when required checks are not met.
 */
class RequirementsStep extends AbstractStep
{
    protected string $key = 'requirements';

    protected int $defaultPriority = 20;

    public function __construct(private readonly RequirementsChecker $checker) {}

    public function run(InstallerContext $context): void
    {
        $report = $this->checker->all();

        $context->set('requirements', $report);

        if (! $report['passes']) {
            throw RequirementsException::fromReport($report);
        }
    }
}
