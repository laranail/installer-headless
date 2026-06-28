<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/installer-run-' . uniqid();
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

    // Register the fixtures migration path so the migrate step creates the
    // users table on whichever connection the environment step switches to.
    $this->app['migrator']->path(__DIR__ . '/../Fixtures/migrations');

    (new InstallationState)->clear();
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

function installContext(string $dbFile): InstallerContext
{
    return InstallerContext::fromInput([
        'locale' => 'en',
        'app_name' => 'Test App',
        'app_url' => 'http://test.local',
        'database_driver' => 'sqlite',
        'database_name' => $dbFile,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ]);
}

it('provisions a fresh app end to end (headless, sqlite)', function (): void {
    $dbFile = $this->dir . '/db.sqlite';

    app(InstallerEngine::class)->run(installContext($dbFile));

    $env = file_get_contents($this->dir . '/.env');

    expect($env)->toContain('APP_NAME="Test App"')
        ->and($env)->toContain('DB_CONNECTION=sqlite')
        ->and($env)->toContain('DB_DATABASE=' . $dbFile)
        ->and(config('database.default'))->toBe('sqlite')
        ->and(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue()
        ->and((new InstallationState)->isInstalled())->toBeTrue();
});

it('is resumable and idempotent on re-run', function (): void {
    $dbFile = $this->dir . '/db.sqlite';
    $engine = app(InstallerEngine::class);

    $engine->run(installContext($dbFile));
    $engine->run(installContext($dbFile));

    expect(User::query()->where('email', 'ada@example.com')->count())->toBe(1);
});
