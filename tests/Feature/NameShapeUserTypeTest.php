<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;
use Simtabi\Laranail\Installer\Headless\Users\UserAccountCreator;
use Simtabi\Laranail\Installer\Headless\Users\UserData;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

function makeUsersTable(): void
{
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->timestamps();
    });

    config()->set('installer.user.model', User::class);
    config()->set('installer.user.fields', [
        'name' => 'name', 'first_name' => 'first_name', 'last_name' => 'last_name',
        'email' => 'email', 'password' => 'password',
    ]);
}

afterEach(fn () => Schema::dropIfExists('users'));

function userStepFieldNames(): array
{
    return array_map(static fn (Field $f): string => $f->name, app(StepRegistry::class)->get('user')->fields());
}

it('uses a single name field by default', function (): void {
    expect(userStepFieldNames())->toContain('name')->not->toContain('first_name');
});

it('uses split first/last name fields when name_shape = split', function (): void {
    config()->set('installer.user.name_shape', 'split');

    expect(userStepFieldNames())->toContain('first_name')->toContain('last_name')->not->toContain('name');

    $rules = app(InstallerEngine::class)->rules('user', []);
    expect($rules)->toHaveKey('first_name')->toHaveKey('last_name');
});

it('persists a split name into first_name/last_name columns', function (): void {
    makeUsersTable();
    config()->set('installer.user.name_shape', 'split');
    config()->set('installer.user.role_driver', 'null');

    $user = (new UserAccountCreator)->create(UserData::fromArray([
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@x.test', 'password' => 'secret-pass',
    ]));

    expect($user->first_name)->toBe('Ada')->and($user->last_name)->toBe('Lovelace');
});

it('assigns no role by default (generic — does not assume admin)', function (): void {
    makeUsersTable();
    config()->set('installer.user.role_driver', 'eloquent');

    $user = (new UserAccountCreator)->create(new UserData('Ada', 'ada@x.test', 'secret-pass'));

    expect($user->role)->toBeNull();
});

it('makes the first user an admin only when opted in', function (): void {
    makeUsersTable();
    config()->set('installer.user.role_driver', 'eloquent');
    config()->set('installer.user.first_user_is_admin', true);

    $user = (new UserAccountCreator)->create(new UserData('Ada', 'ada@x.test', 'secret-pass'));

    expect($user->role)->toBe('admin');
});

it('lets an explicit per-user role win over the first-user policy', function (): void {
    makeUsersTable();
    config()->set('installer.user.role_driver', 'eloquent');
    config()->set('installer.user.first_user_is_admin', true);

    $user = (new UserAccountCreator)->create(new UserData('Ada', 'ada@x.test', 'secret-pass', role: 'editor'));

    expect($user->role)->toBe('editor');
});
