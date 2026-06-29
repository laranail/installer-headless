# Access lockdown

The installer is a high-value target — it carries DB credentials, the admin
password, and (optionally) a licence token, and it can re-provision a live app. The
access layer restricts **who**, **when**, and **how fast** the web wizard can be
reached. Every control is **off by default** (so a default install behaves as before)
and the layer **fails closed**.

Policy lives in the headless package (`Security\InstallerAccessPolicy`, pure and
unit-testable); the web package enforces it with middleware + neutral gate/denied
views. All config is under `installer.security.*` — see
[configuration](../configuration.md).

## Master switch & local dev

| Key | Default | Purpose |
| --- | --- | --- |
| `security.enabled` | `true` | Global kill-switch for the whole layer. |
| `security.bypass_local` | `true` | Skip access checks under the `local` env, so development is frictionless. Set `INSTALLER_SECURITY_BYPASS_LOCAL=false` to exercise lockdown locally. |

Loopback (`127.0.0.1`/`::1`) is always allowed by the IP check outside `production`.

## IP allowlist

```dotenv
INSTALLER_ALLOWED_IPS="203.0.113.7,10.0.0.0/8,2001:db8::/32"
```

Comma-separated IPs and CIDR ranges (IPv4 + IPv6), matched with Symfony `IpUtils`.
Empty = allow all.

> **Behind a proxy/Cloudflare:** the installer resolves the client IP via
> `$request->ip()`, which honours **your app's** `TrustProxies` configuration. Set
> that up (Laravel ships `bootstrap/app.php` → `trustProxies(...)`) or the allowlist
> and throttle will see the proxy's IP, not the visitor's.

## Allowed hosts

```dotenv
INSTALLER_ALLOWED_HOSTS="install.example.com,*.staging.example.com"
```

Rejects requests whose `Host` header isn't listed (wildcards supported). Empty =
allow all.

## HTTPS

```dotenv
INSTALLER_REQUIRE_HTTPS=true
# If TLS terminates at a host proxy and you can't set TrustProxies:
INSTALLER_TRUST_FORWARDED_PROTO=true   # honours X-Forwarded-Proto (spoofable — last resort)
```

## Secret token / password gate

```dotenv
# Prefer the hash (store the raw token nowhere):
INSTALLER_TOKEN_HASH="$2y$12$..."
# or a raw token:
INSTALLER_TOKEN="a-long-random-secret"
INSTALLER_TOKEN_SINGLE_USE=true   # invalidate it after a successful install
```

Generate one (and optionally write it to `.env`):

```bash
php artisan laranail::installer.token --write          # raw INSTALLER_TOKEN
php artisan laranail::installer.token --hash           # hashed INSTALLER_TOKEN_HASH
```

A visitor without a valid token is sent to a **gate** page (`/install/gate`). A token
also passes via the `X-Installer-Token` header or `?token=`. On hosts with no shell,
set the gate password from the browser instead — see
[shared hosting](shared-hosting.md).

### Expiring shareable link

With `INSTALLER_SIGNED_LINKS=true`, a valid `temporarySignedRoute` signature grants
access — hand someone a time-boxed URL:

```php
URL::temporarySignedRoute('installer-web.index', now()->addMinutes(60));
```

## Availability window

```dotenv
INSTALLER_AVAILABLE_FROM="2026-07-01 09:00"
INSTALLER_AVAILABLE_UNTIL="2026-07-01 17:00"
INSTALLER_TIMEZONE="America/New_York"   # defaults to app.timezone
INSTALLER_WINDOW_CLI=false              # also enforce the window on the CLI
```

Open-ended on either side is allowed (only `from`, or only `until`).

## Throttling & gate lockout

```dotenv
INSTALLER_THROTTLE_MAX=60             # wizard requests / decay window
INSTALLER_THROTTLE_DECAY=1           # minutes
INSTALLER_GATE_THROTTLE_MAX=5        # token attempts before lockout
INSTALLER_GATE_LOCKOUT=15            # lockout minutes
```

The gate locks out after repeated failures and fires the security alert (below).

## Response headers

`installer.security.headers` (on by default) adds `Cache-Control: no-store`,
`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy:
no-referrer`, and `X-Robots-Tag: noindex` to every installer response — no caching of
credential forms, no clickjacking, no search indexing.

## Auto-disable after install

`security.disable_after_install` (default `true`): once installed, the wizard routes
are **not registered** (404, not a redirect), the captured wizard input is purged, and
a single-use token is invalidated — so the installer can't be re-run or probed.

## Audit & alerts

Every denial dispatches `UnauthorizedInstallerAccess(reason, ip, path)` (reason is
`ip|host|window|https|token`) — logged to the installer channel. The visitor always
sees a **generic** denial; the reason is recorded server-side only (anti-enumeration).
Opt into email/Slack alerts:

```dotenv
INSTALLER_SECURITY_ALERTS=true
INSTALLER_SECURITY_ALERT_EMAILS="security@example.com"
```

See [events & notifications](events.md).

[← Docs index](../../README.md#documentation)
