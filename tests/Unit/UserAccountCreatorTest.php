<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;
use Simtabi\Laranail\Installer\Headless\Users\UserAccountCreator;
use Simtabi\Laranail\Installer\Headless\Users\UserData;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->timestamps();
    });

    config()->set('installer.user.model', User::class);
    config()->set('installer.user.fields', ['name' => 'name', 'email' => 'email', 'password' => 'password']);
    config()->set('installer.user.role_driver', 'eloquent');
    config()->set('installer.user.role', 'admin');
});

it('creates a user via the field map with a hashed password and role', function (): void {
    $user = (new UserAccountCreator)->create(new UserData('Ada', 'ada@example.com', 'secret-pass'));

    expect($user->email)->toBe('ada@example.com')
        ->and($user->role)->toBe('admin')
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

it('is idempotent for the same email (never duplicates or truncates)', function (): void {
    $creator = new UserAccountCreator;

    $creator->create(new UserData('Ada', 'ada@example.com', 'secret-pass'));
    $creator->create(new UserData('Ada Again', 'ada@example.com', 'secret-pass'));

    expect(User::query()->count())->toBe(1);
});

it('delegates entirely to a configured creator override', function (): void {
    config()->set('installer.user.creator', fn (UserData $data): object => (object) ['email' => strtoupper($data->email)]);

    $user = (new UserAccountCreator)->create(new UserData('X', 'x@example.com', 'pw'));

    expect($user->email)->toBe('X@EXAMPLE.COM')
        ->and(User::query()->count())->toBe(0);
});
