<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an installer access control denies a request — for the audit trail
 * and optional security alerting. The reason is recorded server-side only; the
 * visitor always sees a generic denial (anti-enumeration).
 *
 * @phpstan-type DenyReason 'https'|'host'|'ip'|'window'|'token'|'throttle'
 */
final readonly class UnauthorizedInstallerAccess
{
    use Dispatchable;

    public function __construct(
        public string $reason,
        public ?string $ip = null,
        public string $path = '',
    ) {}
}
