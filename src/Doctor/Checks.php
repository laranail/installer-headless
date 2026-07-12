<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Doctor;

use Simtabi\Laranail\Installer\Headless\Providers\InstallerServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ConfigPresentCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;

/**
 * Doctor checks for the headless installer.
 *
 * The installer publishes a flat `installer` config (the provider calls
 * {@see InstallerServiceProvider::configurePackage()}
 * with `withoutConfigNamespacing()`), so the config array lives under the
 * top-level `installer` key.
 */
final class Checks
{
    /** @return list<DoctorCheck|class-string<DoctorCheck>> */
    public static function all(): array
    {
        return [
            new ConfigPresentCheck(
                ['installer config' => 'installer'],
                required: true,
                name: 'installer:config',
                description: 'Installer config is published',
            ),
        ];
    }
}
