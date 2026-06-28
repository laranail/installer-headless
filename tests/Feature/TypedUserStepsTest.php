<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Steps\CreateUserStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;
use Simtabi\Laranail\Installer\Headless\Users\UserAccountCreator;
use Simtabi\Laranail\Installer\Headless\Users\UserData;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

function freshUsersTable(): void
{
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->timestamps();
    });

    config()->set('installer.user.model', User::class);
    config()->set('installer.user.role_driver', 'eloquent');
}

afterEach(fn () => Schema::dropIfExists('users'));

it('builds a typed user step with its own key, role and label', function (): void {
    $step = new CreateUserStep(key: 'admin-user', role: 'admin', label: 'Administrator');

    expect($step->key())->toBe('admin-user')
        ->and($step->label())->toBe('Administrator');
});

it('a typed step assigns its role to the created user', function (): void {
    freshUsersTable();

    $step = new CreateUserStep(key: 'admin-user', role: 'superadmin');
    $context = InstallerContext::fromInput(['name' => 'Ada', 'email' => 'ada@x.test', 'password' => 'secret-pass']);

    $step->run($context);

    expect(User::query()->where('email', 'ada@x.test')->value('role'))->toBe('superadmin')
        ->and($context->get('admin-user'))->not->toBeNull();
});

it('adds an in-form role picker when installer.user.role_field is set', function (): void {
    config()->set('installer.user.role_field', ['member' => 'Member', 'admin' => 'Admin']);

    $names = array_map(static fn (Field $f): string => $f->name, (new CreateUserStep)->fields());

    expect($names)->toContain('role');
});

it('createMany() bulk-creates users reusing the lifecycle (idempotent by email)', function (): void {
    freshUsersTable();

    $created = app(UserAccountCreator::class)->createMany([
        new UserData('Ada', 'ada@x.test', 'secret-pass', role: 'admin'),
        new UserData('Bo', 'bo@x.test', 'secret-pass', role: 'member'),
        new UserData('Ada Again', 'ada@x.test', 'secret-pass'), // same email → idempotent
    ]);

    expect($created)->toHaveCount(3)
        ->and(User::query()->count())->toBe(2)
        ->and(User::query()->where('email', 'ada@x.test')->value('role'))->toBe('admin');
});
