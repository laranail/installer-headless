# Installation

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `^8.4.1` (8.4, 8.5) |
| Laravel | `^13.0` |
| Runtime deps | `laranail/package-tools`, `laranail/console`, `laranail/license-verifier`, `laravel/prompts` |

## Install

```bash
composer require laranail/installer-headless
```

The `InstallerServiceProvider` is auto-discovered via package discovery.

## Publishing

```bash
# config/installer.php
php artisan vendor:publish --tag=laranail/installer-headless::config

# translations (44 locales)
php artisan vendor:publish --tag=laranail/installer-headless::translations
```

## Optional integrations

- **Web wizard**: `composer require laranail/installer-web`.
- **SQL-dump import**: `composer require laranail/database-tools`.
- **Role assignment**: `composer require spatie/laravel-permission` (auto-detected).

## Dependency resolution (local dev vs CI)

The laranail dependencies are declared as **named** `path` repositories in
`composer.json`, so a local checkout of the sibling packages is symlinked for live
cross-package development:

```
laranail/
├── package/tools          → laranail/package-tools
├── tools/console          → laranail/console
├── licensing/verifier     → laranail/license-verifier
└── installer/headless     → this package
```

Composer 2 errors if a `path` repository's directory is missing, so CI (which has
no sibling checkouts) overrides each by name with its public VCS source before
installing — see `.github/workflows/*.yml`:

```bash
composer config repositories.package-tools vcs https://github.com/laranail/package-tools
composer config repositories.console vcs https://github.com/laranail/console
composer config repositories.license-verifier vcs https://github.com/laranail/license-verifier
composer update --prefer-stable
```

Once these packages are published to Packagist you can drop both the `path` repos
and the CI override entirely. (Consumers installing from Packagist never see any of
this — it only affects developing the packages from source.)

## See also

- [configuration.md](configuration.md) — every config key.
- [architecture.md](architecture.md) — how the engine is put together.

[← Docs index](../README.md#documentation)
