<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Tests\Fixtures;

use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * A license-verifier driver that always reports an invalid license (test double).
 */
final class FailingDriver implements Driver
{
    public function name(): string
    {
        return 'failing';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return VerificationResult::invalid(LicenseStatus::Invalid, 'Invalid purchase code.');
    }

    public function verify(?string $key = null): VerificationResult
    {
        return VerificationResult::invalid(LicenseStatus::Invalid, 'Invalid purchase code.');
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        return false;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        return LicenseInfo::empty();
    }

    public function health(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activationFields(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return [];
    }
}
