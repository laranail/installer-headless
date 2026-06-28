<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/installer-cli-' . uniqid();
    mkdir($this->dir, 0755, true);
    config()->set('installer.env.path', $this->dir . '/.env');
    config()->set('installer.env.example', $this->dir . '/.env.example');
    file_put_contents($this->dir . '/.env.example', "APP_NAME=Example\nDB_CONNECTION=sqlite\n");
    app(InstallationState::class)->clear();
});

afterEach(function (): void {
    app(InstallationState::class)->clear();
    // Product markers are real files in storage_path — clear them unconditionally so a
    // failing product test can't leak state into the next test.
    foreach (['addon', 'a', 'b'] as $product) {
        app(InstallationState::class)->forProduct($product)->clear();
    }
    foreach (array_merge(glob($this->dir . '/*') ?: [], glob($this->dir . '/.*') ?: []) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($this->dir);
});

it('reports status', function (): void {
    $this->artisan('laranail::installer.status')->assertExitCode(0);
});

it('resets installer state', function (): void {
    app(InstallationState::class)->markInstalled();

    $this->artisan('laranail::installer.reset')->assertExitCode(0);

    expect(app(InstallationState::class)->hasInstalledMarker())->toBeFalse();
});

it('updates a single .env key (format-preserving)', function (): void {
    file_put_contents($this->dir . '/.env', "# comment\nAPP_NAME=Old\n");

    $this->artisan('laranail::installer.env', ['key' => 'APP_NAME', 'value' => 'New'])->assertExitCode(0);

    $env = file_get_contents($this->dir . '/.env');
    expect($env)->toContain('# comment')->and($env)->toContain('APP_NAME=New');
});

it('provisions the app headlessly from flags', function (): void {
    config()->set('installer.requirements.permissions', []);
    config()->set('installer.requirements.apache', []);
    config()->set('installer.user.model', User::class);
    config()->set('installer.user.role_driver', 'eloquent');
    $this->app['migrator']->path(__DIR__ . '/../Fixtures/migrations');

    $dbFile = $this->dir . '/db.sqlite';

    $this->artisan('laranail::installer.install', [
        '--db-driver' => 'sqlite',
        '--db-name' => $dbFile,
        '--app-name' => 'CLI App',
        '--app-url' => 'http://cli.test',
        '--user-name' => 'Ada',
        '--user-email' => 'ada@example.com',
        '--user-password' => 'secret-pass',
    ])->assertExitCode(0);

    expect(app(InstallationState::class)->isInstalled())->toBeTrue()
        ->and(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('provisions a single product pipeline via --product (isolated from the app)', function (): void {
    config()->set('installer.requirements.permissions', []);
    config()->set('installer.requirements.apache', []);
    config()->set('installer.products.addon', ['steps' => ['welcome', 'requirements', 'final']]);

    // Field-driven CLI collects only this light pipeline's fields (`locale`, default
    // `en`) — no db/user flags or prompts (proves over-prompting is fixed).
    $this->artisan('laranail::installer.install', ['--product' => 'addon'])->assertExitCode(0);

    expect(app(InstallationState::class)->forProduct('addon')->isInstalled())->toBeTrue()
        ->and(app(InstallationState::class)->isInstalled())->toBeFalse(); // the app itself isn't marked

    app(InstallationState::class)->forProduct('addon')->clear();
});

it('provisions every registered product via --all-products', function (): void {
    config()->set('installer.requirements.permissions', []);
    config()->set('installer.requirements.apache', []);
    config()->set('installer.products', [
        'a' => ['steps' => ['welcome', 'requirements', 'final']],
        'b' => ['steps' => ['requirements', 'final']],
    ]);

    $this->artisan('laranail::installer.install', ['--all-products' => true])->assertExitCode(0);

    expect(app(InstallationState::class)->forProduct('a')->isInstalled())->toBeTrue()
        ->and(app(InstallationState::class)->forProduct('b')->isInstalled())->toBeTrue();

    app(InstallationState::class)->forProduct('a')->clear();
    app(InstallationState::class)->forProduct('b')->clear();
});
