<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Override;
use Simtabi\Laranail\Installer\Headless\Exceptions\LicenseException;
use Simtabi\Laranail\Installer\Headless\Licensing\LicenseStepAdapter;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * License verification step. Off by default; when enabled it delegates activation
 * to laranail/license-verifier via {@see LicenseStepAdapter} (resolved lazily so
 * the disabled path needs no verifier). Honors the skip option when
 * `installer.license.skippable` is true.
 */
class LicenseStep extends AbstractStep
{
    protected string $key = 'license';

    protected int $defaultPriority = 60;

    public function __construct(private readonly InstallationState $state) {}

    #[Override]
    protected function stepFields(): array
    {
        return [
            new Field('purchase_code', 'Purchase code', 'text', '', ['required_without:skip_license', 'string'], sensitive: true),
            new Field('buyer', 'Buyer / username', 'text', '', ['required_without:skip_license', 'string']),
            new Field('skip_license', 'Skip for now', 'checkbox'),
        ];
    }

    public function run(InstallerContext $context): void
    {
        if (! config('installer.license.enabled')) {
            return;
        }

        if (config('installer.license.skippable') && $context->input('skip_license')) {
            ($context->state() ?? $this->state)->remember('license', 'skipped');

            return;
        }

        $result = app(LicenseStepAdapter::class)->activate($context);

        if (! $result->valid) {
            throw LicenseException::activationFailed((string) $result->message);
        }

        ($context->state() ?? $this->state)->remember('license', $result->licensedTo ?? $result->status->value);
        $context->set('license', $result);
    }
}
