<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Support\InstallationState;

beforeEach(function (): void {
    $this->state = new InstallationState;
    $this->state->clear();
});

afterEach(function (): void {
    $this->state->clear();
});

it('is not installed on a fresh app with no markers and no migrations', function (): void {
    expect($this->state->isInstalled())->toBeFalse();
});

it('reports installing while the in-progress marker is fresh', function (): void {
    $this->state->markInstalling();

    expect($this->state->isInstalling())->toBeTrue()
        ->and($this->state->isInstalled())->toBeFalse();
});

it('reports installed once the installed marker is written', function (): void {
    $this->state->markInstalled();

    expect($this->state->hasInstalledMarker())->toBeTrue()
        ->and($this->state->isInstalled())->toBeTrue()
        ->and($this->state->isInstalling())->toBeFalse();
});

it('treats a disabled installer as installed', function (): void {
    config()->set('installer.enabled', false);
    $this->state->flush();

    expect($this->state->isInstalled())->toBeTrue();

    config()->set('installer.enabled', true);
});

it('tracks per-step completion for resume', function (): void {
    expect($this->state->isStepComplete('welcome'))->toBeFalse();

    $this->state->markStepComplete('welcome');

    expect($this->state->isStepComplete('welcome'))->toBeTrue()
        ->and($this->state->completedSteps())->toContain('welcome');
});

it('clears all state', function (): void {
    $this->state->markInstalled();
    $this->state->markStepComplete('welcome');

    $this->state->clear();

    expect($this->state->hasInstalledMarker())->toBeFalse()
        ->and($this->state->completedSteps())->toBe([]);
});

it('exposes a diagnostic status snapshot', function (): void {
    expect($this->state->status())
        ->toHaveKeys(['enabled', 'installed', 'installing', 'installed_marker', 'database_ready', 'app_key']);
});
