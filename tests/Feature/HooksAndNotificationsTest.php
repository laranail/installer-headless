<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFailed;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Events\UnauthorizedInstallerAccess;
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationCompleted;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationFailed;
use Simtabi\Laranail\Installer\Headless\Notifications\UnauthorizedAccessAlert;
use Simtabi\Laranail\Installer\Headless\Users\UserFormHooks;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

it('registers extra user-form fields through the Installer::userFields DSL', function (): void {
    Installer::userFields(fn (?string $role, array $ctx): array => [
        new Field('company', 'Company', 'text', '', ['required', 'string', 'max:120']),
    ]);

    $names = array_map(fn (Field $f): string => $f->name, app(UserFormHooks::class)->resolveFields('admin'));

    expect($names)->toContain('company');
});

it('sends the completion notification when enabled', function (): void {
    config()->set('installer.notifications.enabled', true);
    config()->set('installer.notifications.mail.to', ['ops@example.com']);
    Notification::fake();

    InstallerFinished::dispatch();

    Notification::assertSentOnDemand(InstallationCompleted::class);
});

it('sends nothing when notifications are disabled', function (): void {
    config()->set('installer.notifications.enabled', false);
    Notification::fake();

    InstallerFinished::dispatch();

    Notification::assertNothingSent();
});

it('sends the failure notification on the top-level InstallerFailed event', function (): void {
    config()->set('installer.notifications.enabled', true);
    config()->set('installer.notifications.mail.to', ['ops@example.com']);
    Notification::fake();

    InstallerFailed::dispatch('migrate', new RuntimeException('boom'));

    Notification::assertSentOnDemand(InstallationFailed::class);
});

it('sends the security alert only when the security stream is enabled', function (): void {
    config()->set('installer.notifications.security.enabled', true);
    config()->set('installer.notifications.security.to', ['sec@example.com']);
    Notification::fake();

    UnauthorizedInstallerAccess::dispatch('ip', '203.0.113.7', '/install');

    Notification::assertSentOnDemand(UnauthorizedAccessAlert::class);
});

it('does not send a security alert when that stream is disabled', function (): void {
    config()->set('installer.notifications.security.enabled', false);
    config()->set('installer.notifications.enabled', true);
    config()->set('installer.notifications.mail.to', ['ops@example.com']);
    Notification::fake();

    UnauthorizedInstallerAccess::dispatch('token', '203.0.113.7', '/install');

    Notification::assertNothingSent();
});
