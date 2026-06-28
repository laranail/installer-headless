# laranail/installer-headless

[![Tests](https://github.com/laranail/installer-headless/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/installer-headless/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/installer-headless/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/installer-headless/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Headless installer engine for any Laravel app — requirements checks, `.env`
> generate/update, DB connection test, migrations/seeder, user creation, a
> pluggable step pipeline, an install-once lock, and a full CLI/TUI. The web UI
> ships separately as [`laranail/installer-web`](https://opensource.simtabi.com/installer-web/).

This package contains **all** the install logic and no UI coupling, so it runs
fully headless (CI, Docker, servers with no browser) and can be driven by any
front end through a small public API.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [The step pipeline](#the-step-pipeline)
- [License verification](#license-verification)
- [Documentation](#documentation)
- [Local development](#local-development)
- [Sister packages](#sister-packages)
- [Contributing & security](#contributing--security)
- [License](#license)

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `^8.4.1` (8.4, 8.5) |
| Laravel | `^13.0` |
| Depends on | `laranail/package-tools`, `laranail/console`, `laranail/license-verifier` |

## Installation

```bash
composer require laranail/installer-headless
```

The service provider is auto-discovered. Publish the config to customize:

```bash
php artisan vendor:publish --tag=laranail/installer-headless::config
```

## Quick start

Web wizard: install [`laranail/installer-web`](https://opensource.simtabi.com/installer-web/)
and visit `/install`.

Headless (CI / Docker):

```bash
php artisan laranail::installer.install \
  --db-driver=mysql --db-host=127.0.0.1 --db-name=app \
  --db-username=root --db-password=secret \
  --app-name="My App" --app-url=https://example.com \
  --user-name="Ada" --user-email=ada@example.com --user-password=secret \
  --no-interaction
```

Programmatic (any front end):

```php
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;

app(InstallerEngine::class)->run(InstallerContext::fromInput($validatedInput));
```

## The step pipeline

The default pipeline (each step is config-toggleable and reorderable):

`welcome` → `requirements` → `environment` → `migrate` → `user` → `license`
(off by default) → `final`.

Steps are held in a `StepRegistry` you can extend at runtime — register, insert
(`before`/`after`), reorder (priority), disable, or replace a step without
editing the package. A generic, working `ChoiceStep` ships disabled for re-adding
theme/preset-style selection from config alone. See
[docs/tools/steps.md](docs/tools/steps.md).

## License verification

Off by default (open-source friendly). When enabled, the license step delegates
to [`laranail/license-verifier`](https://opensource.simtabi.com/license-verifier/)
— pick any driver (Envato, Keygen, Lemon Squeezy, …) via that package's config.
See [docs/tools/license.md](docs/tools/license.md).

## Documentation

Hosted at `opensource.simtabi.com/installer-headless/docs/`; the same pages live
under `docs/`:

- [Installation](docs/installation.md) — requirements, install, publishing
- [Configuration](docs/configuration.md) — every `installer.*` config key
- [Architecture](docs/architecture.md) — engine, steps, state, the two-package boundary
- [Wizard engine](docs/tools/wizard.md) — one definition (fields + rules) drives CLI + web; single validation path
- [Release](docs/release.md) — tag-driven releases
- [CLI & TUI](docs/tools/cli.md) — every `laranail::installer.*` command
- [Environment files](docs/tools/env.md) — `EnvFile`/`EnvWriter` (generate + update)
- [Extending steps](docs/tools/steps.md) — add/insert/reorder/replace; `ChoiceStep`; per-product pipelines
- [Runtime DSL](docs/tools/extending.md) — the `Installer` facade: reshape steps/fields/hooks/pipelines from your provider
- [License drivers](docs/tools/license.md) — enable, swap driver, capabilities

## Local development

```bash
composer install
composer test      # Pest
composer lint      # Pint + PHPStan + Rector (dry run)
composer format    # apply Pint
```

## Sister packages

- [`laranail/installer-web`](https://github.com/laranail/installer-web) — the web wizard UI
- [`laranail/license-verifier`](https://github.com/laranail/license-verifier) — license verification
- [`laranail/database-tools`](https://github.com/laranail/database-tools) — optional SQL-dump import

## Contributing & security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (→ opensource@simtabi.com).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
