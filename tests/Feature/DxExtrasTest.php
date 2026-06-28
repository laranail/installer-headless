<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Events\StepFailed;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationCompleted;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationFailed;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;
use Simtabi\Laranail\Installer\Headless\Users\UserAccountCreator;
use Simtabi\Laranail\Installer\Headless\Users\UserCreationHooks;
use Simtabi\Laranail\Installer\Headless\Users\UserData;

it('runs the user-creation hooks pipeline', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->timestamps();
    });

    config()->set('installer.user.model', User::class);

    $created = null;
    app(UserCreationHooks::class)
        ->preparing(fn (array $attrs, UserData $d): array => $attrs + ['role' => 'owner'])
        ->roleAssigning(fn (object $u, string $role): bool => true) // take over (skip default driver)
        ->created(function (object $u, UserData $d) use (&$created): void {
            $created = $u->email;
        });

    $user = app(UserAccountCreator::class)->create(new UserData('Ada', 'ada@example.com', 'secret-pass'));

    expect($user->role)->toBe('owner')
        ->and($created)->toBe('ada@example.com');
});

it('lets a creating hook fully override user creation', function (): void {
    config()->set('installer.user.model', User::class);
    config()->set('installer.user.role'); // skip role assignment for the stand-in object

    app(UserCreationHooks::class)->creating(fn (UserData $d): object => (object) ['email' => 'hooked@example.com', 'role' => null]);

    $user = app(UserAccountCreator::class)->create(new UserData('X', 'x@example.com', 'pw'));

    expect($user->email)->toBe('hooked@example.com');
});

it('sends completion + failure notifications when enabled', function (): void {
    Notification::fake();
    config()->set('installer.notifications.enabled', true);
    config()->set('installer.notifications.mail.to', ['ops@example.com']);

    InstallerFinished::dispatch();
    StepFailed::dispatch('migrate', new RuntimeException('boom'));

    Notification::assertSentOnDemand(InstallationCompleted::class);
    Notification::assertSentOnDemand(InstallationFailed::class);
});

it('sends nothing when notifications are disabled', function (): void {
    Notification::fake();
    config()->set('installer.notifications.enabled', false);

    InstallerFinished::dispatch();

    Notification::assertNothingSent();
});
