<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Notifications\Concerns;

/**
 * Channel selection driven by `installer.notifications.channels` (default `['mail']`),
 * so a consumer can add `slack`/`database`/custom channels via config without editing
 * the notification. Pair each enabled channel with a route in
 * `installer.notifications.routes` (the `mail` channel uses `notifications.mail.to`).
 */
trait RoutesViaConfiguredChannels
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = array_values(array_filter(
            (array) config('installer.notifications.channels', ['mail']),
            static fn ($c): bool => is_string($c) && $c !== '',
        ));

        return $channels === [] ? ['mail'] : $channels;
    }
}
