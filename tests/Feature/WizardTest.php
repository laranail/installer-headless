<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Steps\EnvironmentStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;

beforeEach(fn () => app(InstallationState::class)->clear());
afterEach(fn () => app(InstallationState::class)->clear());

it('derives rules from visible fields only (single source)', function (): void {
    $engine = app(InstallerEngine::class);
    $step = app(EnvironmentStep::class);

    // engine exposes exactly the step's own rules — one source.
    expect($engine->rules('environment', ['database_driver' => 'mysql']))
        ->toBe($step->rules(['database_driver' => 'mysql']));

    // sqlite hides host/port/username/password → their rules drop out.
    expect($engine->rules('environment', ['database_driver' => 'sqlite']))
        ->not->toHaveKey('database_host')
        ->and($engine->rules('environment', ['database_driver' => 'mysql']))
        ->toHaveKey('database_host');
});

it('validates through the single path and rejects bad input', function (): void {
    $engine = app(InstallerEngine::class);

    expect(fn () => $engine->submit('welcome', ['locale' => 'not-a-locale']))
        ->toThrow(ValidationException::class);
});

it('enforces ordering guards (cannot jump ahead)', function (): void {
    $engine = app(InstallerEngine::class);

    expect($engine->canAccess('welcome'))->toBeTrue()
        ->and($engine->canAccess('user'))->toBeFalse();
});

it('exposes navigation and progress', function (): void {
    $engine = app(InstallerEngine::class);

    expect($engine->current()?->key())->toBe('welcome')
        ->and($engine->previous('requirements')?->key())->toBe('welcome')
        ->and($engine->progress())->toMatchArray(['current' => 'welcome'])
        ->and($engine->progress()['total'])->toBeGreaterThan(0);
});

it('persists non-sensitive input but drops secrets by default', function (): void {
    $state = app(InstallationState::class);

    $state->rememberInput('environment', ['app_name' => 'Acme', 'database_password' => 'super-secret']);

    expect($state->recallInput('environment'))
        ->toHaveKey('app_name')
        ->not->toHaveKey('database_password');
});

it('persists secrets encrypted when enabled', function (): void {
    config()->set('installer.wizard.persist_secrets', true);
    $state = app(InstallationState::class);

    $state->rememberInput('environment', ['app_name' => 'Acme', 'database_password' => 'super-secret']);

    expect($state->recallInput('environment'))->toMatchArray([
        'app_name' => 'Acme',
        'database_password' => 'super-secret',
    ]);

    // raw state file must not contain the plaintext secret.
    $file = storage_path('installer.state.json');
    expect(file_get_contents($file))->not->toContain('super-secret');
});

it('re-hydrates values over defaults', function (): void {
    $engine = app(InstallerEngine::class);
    app(InstallationState::class)->rememberInput('environment', ['app_name' => 'Persisted']);

    expect($engine->values('environment')['app_name'])->toBe('Persisted');
});
