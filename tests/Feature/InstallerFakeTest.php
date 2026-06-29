<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/installer-fake-' . uniqid();
    mkdir($this->dir, 0755, true);
    file_put_contents($this->dir . '/.env.example', "APP_NAME=Example\nAPP_URL=http://localhost\nDB_CONNECTION=sqlite\nDB_DATABASE=\n");

    config()->set('installer.env.path', $this->dir . '/.env');
    config()->set('installer.env.example', $this->dir . '/.env.example');
    config()->set('installer.requirements.permissions', []);
    config()->set('installer.requirements.apache', []);
    config()->set('installer.user.model', User::class);
    config()->set('installer.user.fields', ['name' => 'name', 'email' => 'email', 'password' => 'password']);
    config()->set('installer.user.role_driver', 'eloquent');
    config()->set('installer.user.role', 'admin');

    $this->app['migrator']->path(__DIR__ . '/../Fixtures/migrations');
    (new InstallationState)->clear();

    $this->context = fn (): InstallerContext => InstallerContext::fromInput([
        'locale' => 'en',
        'app_name' => 'Test App',
        'app_url' => 'http://test.local',
        'database_driver' => 'sqlite',
        'database_name' => $this->dir . '/db.sqlite',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ]);
});

afterEach(function (): void {
    (new InstallationState)->clear();
    foreach (array_merge(glob($this->dir . '/*') ?: [], glob($this->dir . '/.*') ?: []) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($this->dir);
});

it('records lifecycle events for assertions via Installer::fake()', function (): void {
    $fake = Installer::fake();

    app(InstallerEngine::class)->run(($this->context)());

    $fake->assertStarted()
        ->assertStepCompleted('environment')
        ->assertUserCreated(fn ($e): bool => $e->data->email === 'ada@example.com')
        ->assertFinished();
});

it('asserts failure and not-finished when a step throws', function (): void {
    config()->set('installer.user.model', stdClass::class);

    $fake = Installer::fake();

    expect(fn () => app(InstallerEngine::class)->run(($this->context)()))
        ->toThrow(InstallerException::class);

    $fake->assertFailed()->assertNotFinished();
});
