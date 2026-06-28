<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Exceptions;

/**
 * Thrown when the .env file cannot be read or written.
 */
final class EnvironmentException extends InstallerException
{
    public static function unreadable(string $path): self
    {
        return new self("Unable to read environment file at [{$path}].");
    }

    public static function unwritable(string $path): self
    {
        return new self("Unable to write environment file at [{$path}].");
    }
}
