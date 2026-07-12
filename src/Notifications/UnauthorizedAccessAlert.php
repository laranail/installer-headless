<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Simtabi\Laranail\Installer\Headless\Notifications\Concerns\RoutesViaConfiguredChannels;

/**
 * Security alert sent when an installer access control denies a request (IP, token,
 * window, host, HTTPS, or a gate lockout). Off by default — enable via
 * `installer.notifications.security.enabled` with recipients in
 * `installer.notifications.security.to`.
 */
final class UnauthorizedAccessAlert extends Notification
{
    use RoutesViaConfiguredChannels;

    public function __construct(
        public readonly string $reason,
        public readonly ?string $ip = null,
        public readonly string $path = '',
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Installer access denied — ' . config('app.name', 'Application'))
            ->greeting('Unauthorized installer access')
            ->line("A request to the installer was blocked (reason: {$this->reason}).")
            ->line('Source IP: ' . ($this->ip ?? 'unknown'))
            ->line('Path: ' . ($this->path !== '' ? $this->path : 'n/a'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['event' => 'installer.unauthorized', 'reason' => $this->reason, 'ip' => $this->ip, 'path' => $this->path];
    }
}
