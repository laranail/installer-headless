<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Security;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Pure access-control policy for the installer — no HTTP, so it's unit-testable and
 * reusable by the CLI. Reads `installer.security.*`; every control is off by default
 * and the policy fails closed. The web middleware passes request primitives in.
 */
final class InstallerAccessPolicy
{
    /** The whole layer is engaged (master switch on and not locally bypassed). */
    public function enabled(): bool
    {
        return (bool) config('installer.security.enabled', true);
    }

    public function bypassed(): bool
    {
        return (bool) config('installer.security.bypass_local', true)
            && app()->environment('local');
    }

    /** Access is unrestricted (layer disabled, or local-bypassed). */
    public function unrestricted(): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        return $this->bypassed();
    }

    public function httpsOk(bool $secure): bool
    {
        return ! (bool) config('installer.security.require_https', false) || $secure;
    }

    public function hostAllowed(?string $host): bool
    {
        $allowed = $this->list('installer.security.allowed_hosts');

        if ($allowed === []) {
            return true;
        }

        $host = (string) $host;

        return array_any($allowed, fn ($pattern) => Str::is($pattern, $host));
    }

    public function ipAllowed(?string $ip): bool
    {
        $allowed = $this->list('installer.security.allowed_ips');

        if ($allowed === []) {
            return true;
        }

        // Loopback is always allowed outside production so staging/dev with an
        // allowlist still works locally.
        if (! app()->environment('production') && in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowed);
    }

    /** Whether a secret gate is configured (hash preferred, else raw token). */
    public function tokenConfigured(): bool
    {
        if ($this->configured('installer.security.token_hash')) {
            return true;
        }

        return $this->configured('installer.security.token');
    }

    public function tokenValid(?string $provided): bool
    {
        $hash = config('installer.security.token_hash');
        $raw = config('installer.security.token');
        $hasHash = is_string($hash) && $hash !== '';
        $hasRaw = is_string($raw) && $raw !== '';

        if (! $hasHash && ! $hasRaw) {
            return true; // no gate configured
        }

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        // Prefer the hashed form; both comparisons are constant-time.
        return $hasHash ? Hash::check($provided, $hash) : hash_equals($raw, $provided);
    }

    public function windowConfigured(): bool
    {
        if ($this->configured('installer.security.available_from')) {
            return true;
        }

        return $this->configured('installer.security.available_until');
    }

    public function withinWindow(?CarbonInterface $now = null): bool
    {
        if (! $this->windowConfigured()) {
            return true;
        }

        $tz = config('installer.security.timezone') ?: config('app.timezone');
        $now = $now instanceof CarbonInterface ? $now : CarbonImmutable::now($tz);

        $from = config('installer.security.available_from');
        $until = config('installer.security.available_until');

        if (is_string($from) && $from !== '' && $now->lessThan(CarbonImmutable::parse($from, $tz))) {
            return false;
        }

        if (is_string($until) && $until !== '' && $now->greaterThan(CarbonImmutable::parse($until, $tz))) {
            return false;
        }

        return true;
    }

    /**
     * First failing non-token check (`https|host|ip|window`), or null when allowed.
     * The token gate is handled separately by the middleware (it has a form flow);
     * the visitor only ever sees a generic denial — the reason is for the audit log.
     */
    public function denyReason(bool $secure, ?string $host, ?string $ip): ?string
    {
        if ($this->unrestricted()) {
            return null;
        }

        return match (true) {
            ! $this->httpsOk($secure) => 'https',
            ! $this->hostAllowed($host) => 'host',
            ! $this->ipAllowed($ip) => 'ip',
            ! $this->withinWindow() => 'window',
            default => null,
        };
    }

    private function configured(string $key): bool
    {
        $value = config($key);

        return is_string($value) && $value !== '';
    }

    /**
     * @return list<string>
     */
    private function list(string $key): array
    {
        return array_values(array_filter(
            (array) config($key, []),
            static fn ($v): bool => is_string($v) && $v !== '',
        ));
    }
}
