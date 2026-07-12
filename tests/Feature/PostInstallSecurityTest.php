<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Steps\FinalStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;

beforeEach(fn () => app(InstallationState::class)->clear());

afterEach(fn () => app(InstallationState::class)->clear());

it('purges captured wizard input when the install finishes', function (): void {
    $state = app(InstallationState::class);
    $state->rememberInput('environment', ['app_name' => 'Secret App']);

    expect($state->recallInput('environment'))->not->toBe([]);

    app(FinalStep::class)->run(InstallerContext::fromInput([]));

    expect($state->recallInput('environment'))->toBe([])
        ->and($state->isInstalled())->toBeTrue();
});

it('invalidates a single-use token in .env when the install finishes', function (): void {
    $dir = sys_get_temp_dir() . '/installer-su-' . uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir . '/.env', "APP_NAME=Example\nINSTALLER_TOKEN=sekret-token\n");

    config()->set('installer.env.path', $dir . '/.env');
    config()->set('installer.security.single_use_token', true);
    config()->set('installer.security.token', 'sekret-token');

    app(FinalStep::class)->run(InstallerContext::fromInput([]));

    $env = (string) file_get_contents($dir . '/.env');

    expect($env)->toContain('INSTALLER_TOKEN=')
        ->and($env)->not->toContain('sekret-token');

    @unlink($dir . '/.env');
    @rmdir($dir);
});

it('does not touch .env when single-use mode is off', function (): void {
    $dir = sys_get_temp_dir() . '/installer-su2-' . uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir . '/.env', "APP_NAME=Example\nINSTALLER_TOKEN=keep-me\n");

    config()->set('installer.env.path', $dir . '/.env');
    config()->set('installer.security.single_use_token', false);
    config()->set('installer.security.token', 'keep-me');

    app(FinalStep::class)->run(InstallerContext::fromInput([]));

    expect((string) file_get_contents($dir . '/.env'))->toContain('INSTALLER_TOKEN=keep-me');

    @unlink($dir . '/.env');
    @rmdir($dir);
});

it('requires a valid --token for state-changing commands when a token is configured', function (): void {
    config()->set('installer.security.token', 'sekret-token');

    $this->artisan('laranail::installer.reset')->assertFailed();
    $this->artisan('laranail::installer.reset', ['--token' => 'wrong'])->assertFailed();
    $this->artisan('laranail::installer.reset', ['--token' => 'sekret-token'])->assertSuccessful();
});
