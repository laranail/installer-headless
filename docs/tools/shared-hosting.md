# Shared hosting (cPanel / no SSH)

The installer is built to run on restricted shared hosting — no SSH, limited file
permissions, `proc_open` disabled, no cron/queue workers, low PHP limits. It is fully
operable from the browser and degrades gracefully. None of this is shared-only
behaviour; it's safe on a VPS too.

## What just works

- **No shell-outs.** Migrations run in-process via `Artisan::call`; nothing uses
  `exec`/`proc_open`/`Symfony\Process`, so disabled functions don't matter.
- **No queue/cron dependency.** Notifications send synchronously during the request.
- **Pre-created database.** The DB step connects to the database you created in
  cPanel; it never issues `CREATE DATABASE` (no special privilege needed).
- **Atomic, permission-tolerant `.env` writes** (temp file in the same directory; a
  `copy()` fallback when `rename()` is blocked; `chmod` is best-effort).

## Set this in `.env` (via the File Manager)

A stock Laravel app defaults to database-backed session/cache, whose tables don't
exist until migrations run — which would 500 the wizard's first request. The installer
**forces file stores for its own routes** automatically; you can tune or disable it:

```dotenv
INSTALLER_SESSION_STORE=file     # null to leave the app's driver untouched
INSTALLER_CACHE_STORE=file
INSTALLER_ENV_MODE=auto          # auto | shared | vps
INSTALLER_TIME_LIMIT=0           # raise max_execution_time for long steps (0 = unlimited)
```

## Permissions

In the cPanel File Manager, make these writable (typically `775`):
`storage/`, `bootstrap/cache/`, and the project root (so `.env` can be created). The
requirements step checks these and the parent directory of a not-yet-created `.env`,
and shows warnings (not hard failures) for low `max_execution_time`/`memory_limit` and
db-backed session/cache.

## Securing it without SSH

You can't run `php artisan laranail::installer.token` without a shell — so set the gate
from the browser. Visit **`/install/setup`** to:

- set a **gate password** (stored as `INSTALLER_TOKEN_HASH` in `.env`), and/or
- **lock the installer to your current IP** (written to `INSTALLER_ALLOWED_IPS`).

See [access lockdown](security.md) for the full control set.

## Large database imports

`import-database` runs the dump in one request, which can exceed a low
`max_execution_time`. For large dumps, import via the host's **phpMyAdmin** instead
and leave the step disabled (it's off by default). `import-users` streams its CSV
(bounded memory) and is idempotent by email, so a retry is safe.

## HTTPS behind the host's proxy

If the host terminates TLS at a proxy and you can't configure Laravel's TrustProxies,
`INSTALLER_TRUST_FORWARDED_PROTO=true` lets `require_https` honour `X-Forwarded-Proto`
(spoofable — a last resort).

[← Docs index](../../README.md#documentation)
