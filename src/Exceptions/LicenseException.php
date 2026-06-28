<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Exceptions;

/**
 * Thrown when license activation/verification fails during installation.
 */
final class LicenseException extends InstallerException
{
    public static function activationFailed(string $message): self
    {
        return new self($message !== '' ? $message : 'License activation failed.');
    }
}
