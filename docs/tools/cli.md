# CLI & TUI

All commands use the `laranail::installer.<command>` naming shape.

## `laranail::installer.install`

Runs the full pipeline. Non-interactive from flags/env (CI, Docker); interactive
prompts (Laravel Prompts) fill any missing values when run in a TTY.

```bash
php artisan laranail::installer.install \
  --app-name="My App" --app-url=https://example.com \
  --db-driver=mysql --db-host=127.0.0.1 --db-port=3306 \
  --db-name=app --db-username=root --db-password=secret \
  --user-name="Ada" --user-email=ada@example.com --user-password=secret \
  --locale=en --no-interaction
```

| Option | Notes |
|---|---|
| `--db-driver` | `mysql` \| `mariadb` \| `pgsql` \| `sqlsrv` \| `sqlite` |
| `--db-name` | database name, or the file path for sqlite |
| `--product=<slug>` | install one product's pipeline (see [steps.md](steps.md#multi-product-installs-per-product-pipeline-orchestrator)) |
| `--all-products` | install every registered product, each with isolated state |
| `--force` | re-run even if already installed |

The command renders a **live install dashboard** — a progress bar over the steps
with per-step labels (and `(skipped)` for already-completed steps on resume) — via
`laranail/console`'s `ProgressReporter` seam (`app(ProgressReporter::class)->run(...)`).
By default it uses the `laravel/prompts` renderer, which degrades gracefully to plain
output when non-interactive (e.g. CI).

> **Optional full-screen TUI.** The same dashboard can render through the experimental
> `symfony/tui` component without changing the command. It stays an *optional*
> dependency (Symfony's Tui component is experimental, no BC promise), so the prompts
> renderer is the default. To switch: `composer require symfony/tui` and set
> `console.tui.progress = true` (see laranail/console's TUI docs). Nothing in the
> installer depends on symfony/tui being present.

## `laranail::installer.status`

Prints install markers, DB readiness, `APP_KEY` presence and completed steps.

## `laranail::installer.reset`

Clears installer state so the wizard can run again. Refuses to run in production
without `--force`.

## `laranail::installer.env {key} {value}`

Sets a single `.env` key, preserving comments/formatting, written atomically.

License operations (activate/verify/deactivate/status) are provided by
`laranail/license-verifier`'s own commands and are not duplicated here.

[← Docs index](../../README.md#documentation)
