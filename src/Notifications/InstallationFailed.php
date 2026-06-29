<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Simtabi\Laranail\Installer\Headless\Notifications\Concerns\RoutesViaConfiguredChannels;

/**
 * Sent when the installation fails. Channels come from
 * `installer.notifications.channels` (default `mail`).
 */
final class InstallationFailed extends Notification
{
    use RoutesViaConfiguredChannels;

    public function __construct(public readonly string $step, public readonly string $reason) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Installation failed — ' . config('app.name', 'Application'))
            ->greeting('Installation failed')
            ->line("The installer failed during the [{$this->step}] step.")
            ->line($this->reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['event' => 'installer.failed', 'step' => $this->step, 'reason' => $this->reason];
    }
}
