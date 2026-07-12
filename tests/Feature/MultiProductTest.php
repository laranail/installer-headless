<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;

afterEach(function (): void {
    $state = app(InstallationState::class);
    $state->forProduct('alpha')->clear();
    $state->forProduct('beta')->clear();
});

it('isolates install + step state per product', function (): void {
    $alpha = app(InstallationState::class)->forProduct('alpha');
    $beta = app(InstallationState::class)->forProduct('beta');
    $alpha->clear();
    $beta->clear();

    $alpha->markInstalled();
    $alpha->markStepComplete('welcome');

    expect($alpha->isInstalled())->toBeTrue()
        ->and($beta->isInstalled())->toBeFalse()
        ->and($alpha->isStepComplete('welcome'))->toBeTrue()
        ->and($beta->isStepComplete('welcome'))->toBeFalse();
});

it('exposes a product-scoped engine', function (): void {
    expect(app(InstallerEngine::class)->forProduct('alpha'))->toBeInstanceOf(InstallerEngine::class);
});
