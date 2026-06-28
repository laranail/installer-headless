<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Providers\InstallerServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(InstallerServiceProvider::class);
});

it('merges the flat installer config', function (): void {
    expect(config('installer'))->toBeArray()
        ->and(config('installer.enabled'))->toBeTrue()
        ->and(config('installer.steps.welcome.priority'))->toBe(10);
});

it('registers the installer translation namespace', function (): void {
    expect(trans()->hasForLocale('installer::installer', 'en'))->toBeBool();
});
