<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Simtabi\Laranail\Installer\Headless\Security\InstallerAccessPolicy;

beforeEach(function (): void {
    $this->policy = new InstallerAccessPolicy;
});

it('is unrestricted when the layer is disabled', function (): void {
    config()->set('installer.security.enabled', false);

    expect($this->policy->unrestricted())->toBeTrue()
        ->and($this->policy->denyReason(false, 'evil.test', '9.9.9.9'))->toBeNull();
});

it('is unrestricted (bypassed) under the local environment', function (): void {
    $this->app['env'] = 'local';
    config()->set('installer.security.bypass_local', true);

    expect($this->policy->bypassed())->toBeTrue()
        ->and($this->policy->unrestricted())->toBeTrue();
});

it('enforces HTTPS only when required', function (): void {
    config()->set('installer.security.require_https', false);
    expect($this->policy->httpsOk(false))->toBeTrue();

    config()->set('installer.security.require_https', true);
    expect($this->policy->httpsOk(false))->toBeFalse()
        ->and($this->policy->httpsOk(true))->toBeTrue();
});

it('allows hosts by exact match and wildcard, all when empty', function (): void {
    config()->set('installer.security.allowed_hosts', []);
    expect($this->policy->hostAllowed('anything.test'))->toBeTrue();

    config()->set('installer.security.allowed_hosts', ['install.example.com', '*.staging.example.com']);
    expect($this->policy->hostAllowed('install.example.com'))->toBeTrue()
        ->and($this->policy->hostAllowed('a.staging.example.com'))->toBeTrue()
        ->and($this->policy->hostAllowed('evil.test'))->toBeFalse();
});

it('allows IPs by exact and CIDR match, all when empty, loopback off-prod', function (): void {
    config()->set('installer.security.allowed_ips', []);
    expect($this->policy->ipAllowed('203.0.113.7'))->toBeTrue();

    config()->set('installer.security.allowed_ips', ['203.0.113.7', '10.0.0.0/8']);
    expect($this->policy->ipAllowed('203.0.113.7'))->toBeTrue()
        ->and($this->policy->ipAllowed('10.1.2.3'))->toBeTrue()
        ->and($this->policy->ipAllowed('198.51.100.1'))->toBeFalse()
        // loopback exempt outside production (env=testing here)
        ->and($this->policy->ipAllowed('127.0.0.1'))->toBeTrue();
});

it('validates a raw token with constant-time comparison', function (): void {
    config()->set('installer.security.token', 's3cr3t-token');

    expect($this->policy->tokenConfigured())->toBeTrue()
        ->and($this->policy->tokenValid('s3cr3t-token'))->toBeTrue()
        ->and($this->policy->tokenValid('wrong'))->toBeFalse()
        ->and($this->policy->tokenValid(null))->toBeFalse()
        ->and($this->policy->tokenValid(''))->toBeFalse();
});

it('validates a hashed token, preferring the hash over the raw', function (): void {
    config()->set('installer.security.token', 'ignored-raw');
    config()->set('installer.security.token_hash', Hash::make('hashed-secret'));

    expect($this->policy->tokenValid('hashed-secret'))->toBeTrue()
        ->and($this->policy->tokenValid('ignored-raw'))->toBeFalse();
});

it('passes the token check when no gate is configured', function (): void {
    config()->set('installer.security.token');
    config()->set('installer.security.token_hash');

    expect($this->policy->tokenConfigured())->toBeFalse()
        ->and($this->policy->tokenValid(null))->toBeTrue();
});

it('honours an availability window in the configured timezone', function (): void {
    config()->set('installer.security.timezone', 'UTC');
    config()->set('installer.security.available_from', '2026-06-01 00:00');
    config()->set('installer.security.available_until', '2026-06-30 23:59');

    expect($this->policy->withinWindow(CarbonImmutable::parse('2026-06-15 12:00', 'UTC')))->toBeTrue()
        ->and($this->policy->withinWindow(CarbonImmutable::parse('2026-07-01 12:00', 'UTC')))->toBeFalse()
        ->and($this->policy->withinWindow(CarbonImmutable::parse('2026-05-01 12:00', 'UTC')))->toBeFalse();

    config()->set('installer.security.available_from');
    config()->set('installer.security.available_until');
    expect($this->policy->withinWindow())->toBeTrue();
});

it('reports the first failing reason in order', function (): void {
    config()->set('installer.security.require_https', true);
    config()->set('installer.security.allowed_ips', ['10.0.0.0/8']);

    expect($this->policy->denyReason(false, null, '1.2.3.4'))->toBe('https')
        ->and($this->policy->denyReason(true, null, '1.2.3.4'))->toBe('ip')
        ->and($this->policy->denyReason(true, null, '10.1.2.3'))->toBeNull();
});
