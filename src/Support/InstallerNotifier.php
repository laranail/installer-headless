<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFailed;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Events\UnauthorizedInstallerAccess;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationCompleted;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationFailed;
use Simtabi\Laranail\Installer\Headless\Notifications\UnauthorizedAccessAlert;
use Throwable;

/**
 * Sends install completion/failure and security-alert notifications to the
 * configured recipients. Off by default. Channels are pluggable via
 * `installer.notifications.channels`; the `mail` channel routes to
 * `notifications.mail.to`, other channels to `notifications.routes.<channel>`.
 * Sends synchronously (no queue worker required) and never lets a failing
 * transport break the install — the send is wrapped and logged.
 */
final class InstallerNotifier
{
    public function handleFinished(): void
    {
        $this->dispatch(
            new InstallationCompleted,
            (bool) config('installer.notifications.enabled', false),
            (array) config('installer.notifications.mail.to', []),
        );
    }

    public function handleFailed(InstallerFailed $event): void
    {
        $this->dispatch(
            new InstallationFailed($event->step ?? 'unknown', $event->exception->getMessage()),
            (bool) config('installer.notifications.enabled', false),
            (array) config('installer.notifications.mail.to', []),
        );
    }

    public function handleUnauthorized(UnauthorizedInstallerAccess $event): void
    {
        $this->dispatch(
            new UnauthorizedAccessAlert($event->reason, $event->ip, $event->path),
            (bool) config('installer.notifications.security.enabled', false),
            (array) config('installer.notifications.security.to', []),
        );
    }

    public function subscribe(): array
    {
        return [
            InstallerFinished::class => 'handleFinished',
            InstallerFailed::class => 'handleFailed',
            UnauthorizedInstallerAccess::class => 'handleUnauthorized',
        ];
    }

    /**
     * @param  array<int, mixed>  $mailTo
     */
    private function dispatch(object $notification, bool $enabled, array $mailTo): void
    {
        if (! $enabled) {
            return;
        }

        $mailTo = array_values(array_filter($mailTo, static fn ($v): bool => is_string($v) && $v !== ''));
        $routes = (array) config('installer.notifications.routes', []);

        if ($mailTo === [] && $routes === []) {
            return;
        }

        $notifiable = new AnonymousNotifiable;

        if ($mailTo !== []) {
            $notifiable->route('mail', $mailTo);
        }

        foreach ($routes as $channel => $route) {
            $notifiable->route((string) $channel, $route);
        }

        try {
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            $channel = config('installer.logging.channel');
            Log::channel(is_string($channel) ? $channel : null)
                ->warning('installer: notification dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
