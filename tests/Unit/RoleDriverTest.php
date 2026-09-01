<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\EloquentRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\NullRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleDrivers\SpatieRoleDriver;
use Simtabi\Laranail\Installer\Headless\Users\RoleManager;

it('null driver is a harmless no-op', function (): void {
    $user = (object) ['role' => null];

    (new NullRoleDriver)->assign($user, 'admin');

    expect($user->role)->toBeNull();
});

it('eloquent driver calls assignRole when available', function (): void {
    $user = new class
    {
        public ?string $assigned = null;

        public function assignRole(string $role): void
        {
            $this->assigned = $role;
        }
    };

    (new EloquentRoleDriver)->assign($user, 'admin');

    expect($user->assigned)->toBe('admin');
});

it('eloquent driver sets a role column when present', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->string('role')->nullable();
        $table->timestamps();
    });

    $user = User::query()->create(['email' => 'a@b.com']);

    (new EloquentRoleDriver)->assign($user, 'admin');

    expect($user->fresh()->role)->toBe('admin');
});

it('role manager resolves explicit drivers and auto-detects spatie', function (): void {
    // Fresh instances so the Manager's per-instance driver cache doesn't carry over.
    config()->set('installer.user.role_driver', 'null');
    expect(new RoleManager(app())->resolve())->toBeInstanceOf(NullRoleDriver::class);

    config()->set('installer.user.role_driver', 'eloquent');
    expect(new RoleManager(app())->resolve())->toBeInstanceOf(EloquentRoleDriver::class);

    // spatie/laravel-permission is installed in dev, so auto-detect picks it.
    config()->set('installer.user.role_driver');
    expect(new RoleManager(app())->resolve())->toBeInstanceOf(SpatieRoleDriver::class);
});

it('lets a consumer register a custom role driver at runtime via extend()', function (): void {
    config()->set('installer.user.role_driver', 'vault');

    $manager = new RoleManager(app());
    $manager->extend('vault', fn (): NullRoleDriver => new NullRoleDriver);

    expect($manager->resolve())->toBeInstanceOf(NullRoleDriver::class);
});
