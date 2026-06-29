<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Simtabi\Laranail\Installer\Headless\Notifications\Concerns\RoutesViaConfiguredChannels;

/**
 * Sent when installation completes successfully. Channels come from
 * `installer.notifications.channels` (default `mail`); uses the default MailMessage
 * template, so no view files ship with the package.
 */
final class InstallationCompleted extends Notification
{
    use RoutesViaConfiguredChannels;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Installation completed — ' . config('app.name', 'Application'))
            ->greeting('Installation complete')
            ->line(config('app.name', 'The application') . ' has been installed successfully.')
            ->line('You can now sign in and start using it.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['event' => 'installer.completed', 'app' => config('app.name')];
    }
}
