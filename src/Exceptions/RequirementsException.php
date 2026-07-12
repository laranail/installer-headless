<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Exceptions;

/**
 * Thrown when one or more required server requirements are not satisfied.
 */
final class RequirementsException extends InstallerException
{
    /**
     * @param  array{php: array{required:string,current:string,passes:bool}, extensions: array<string,bool>, permissions: array<string,bool>}  $report
     */
    public static function fromReport(array $report): self
    {
        $problems = [];

        if (! $report['php']['passes']) {
            $problems[] = "PHP {$report['php']['required']}+ required, found {$report['php']['current']}";
        }

        foreach ($report['extensions'] as $extension => $ok) {
            if (! $ok) {
                $problems[] = "missing PHP extension: {$extension}";
            }
        }

        foreach ($report['permissions'] as $path => $ok) {
            if (! $ok) {
                $problems[] = "not writable: {$path}";
            }
        }

        return new self('Server requirements not met — ' . implode('; ', $problems) . '.');
    }
}
