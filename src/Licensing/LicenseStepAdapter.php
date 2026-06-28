<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Licensing;

use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Bridges the installer's license step to laranail/license-verifier.
 *
 * The step depends only on this adapter (and, transitively, the verifier's
 * LicenseManager contract) — never a concrete driver. Which driver runs, plus
 * caching/grace/offline behavior, are configured entirely in license-verifier.
 *
 * Capability mapping (the four optional capabilities): activate → here;
 * revalidate → LicenseManager::verify(); deactivate → LicenseManager::deactivate();
 * transfer (domain move) → the active driver implementing {@see SupportsDomainBinding}
 * (gated by {@see supportsTransfer()}). Revalidate/deactivate/transfer are exposed
 * through license-verifier's own commands, not duplicated here.
 */
final readonly class LicenseStepAdapter
{
    public function __construct(private LicenseManager $manager) {}

    /**
     * Activate the license from collected installer input (purchase code + buyer).
     */
    public function activate(InstallerContext $context): VerificationResult
    {
        $request = LicenseRequest::fromArray([
            'license_key' => (string) $context->input('purchase_code', $context->input('license_key', '')),
            'client' => $context->input('buyer', $context->input('client')),
            'metadata' => ['domain' => $context->input('app_url')],
        ]);

        return $this->manager->activate($request);
    }

    /**
     * Whether the active driver supports domain transfer (instanceof gating).
     */
    public function supportsTransfer(): bool
    {
        return app(Driver::class) instanceof SupportsDomainBinding;
    }
}
