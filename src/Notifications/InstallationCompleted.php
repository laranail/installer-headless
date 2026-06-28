<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent (on the `mail` channel) when installation completes successfully. Uses the
 * default MailMessage template, so no view files ship with the package.
 */
final class InstallationCompleted extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Installation completed — ' . config('app.name', 'Application'))
            ->greeting('Installation complete')
            ->line(config('app.name', 'The application') . ' has been installed successfully.')
            ->line('You can now sign in and start using it.');
    }
}
