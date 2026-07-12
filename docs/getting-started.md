# Getting started

Run your first install through the headless engine's CLI. See the
[Documentation index](../README.md#documentation).

## 1. Install

```bash
composer require laranail/installer-headless
php artisan vendor:publish --tag=installer-config
```

See [Installation](installation.md).

## 2. Run the installer

The same wizard definition drives the CLI, the TUI, and the web UI. Run it interactively:

```bash
php artisan laranail::installer.install      # step through requirements → .env → DB → migrate → admin user
```

Each step is a validated unit; on completion the engine writes an install-once lock so the installer
refuses to run again.

## 3. Check status

```bash
php artisan laranail::installer.status
```

## Next steps

- [Wizard engine](tools/wizard.md) — how one definition drives every surface.
- [Extending steps](tools/steps.md) + [Runtime DSL](tools/extending.md) — customise the pipeline.
- [Shared hosting](tools/shared-hosting.md) — cPanel / no-SSH setups.
- For a browser wizard, add [`laranail/installer-web`](https://github.com/laranail/installer-web).

---

[← Docs index](../README.md#documentation)
