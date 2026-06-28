<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Events\StepFailed;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationCompleted;
use Simtabi\Laranail\Installer\Headless\Notifications\InstallationFailed;

/**
 * Sends install completion/failure notifications to the configured recipients.
 * Off by default — enable via `installer.notifications.enabled` and list
 * `installer.notifications.mail.to`. Uses on-demand (routed) notifications, so no
 * notifiable model is required.
 */
final class InstallerNotifier
{
    public function handleFinished(): void
    {
        $this->notify(new InstallationCompleted);
    }

    public function handleFailed(StepFailed $event): void
    {
        $this->notify(new InstallationFailed($event->step, $event->exception->getMessage()));
    }

    public function subscribe(): array
    {
        return [
            InstallerFinished::class => 'handleFinished',
            StepFailed::class => 'handleFailed',
        ];
    }

    private function notify(object $notification): void
    {
        if (! config('installer.notifications.enabled', false)) {
            return;
        }

        $recipients = array_filter((array) config('installer.notifications.mail.to', []));

        if ($recipients === []) {
            return;
        }

        Notification::route('mail', array_values($recipients))->notify($notification);
    }
}
