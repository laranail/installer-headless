<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent (on the `mail` channel) when an installation step fails.
 */
final class InstallationFailed extends Notification
{
    public function __construct(public readonly string $step, public readonly string $reason) {}

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
            ->error()
            ->subject('Installation failed — ' . config('app.name', 'Application'))
            ->greeting('Installation failed')
            ->line("The installer failed during the [{$this->step}] step.")
            ->line($this->reason);
    }
}
