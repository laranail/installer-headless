# Contributing

Thanks for helping improve `laranail/installer-headless`.

## Workflow

1. Fork and branch from `main`.
2. Make your change with tests.
3. Ensure the suite is green:

   ```bash
   composer test      # Pest
   composer lint      # Pint + PHPStan + Rector (dry run)
   ```

4. Open a pull request describing the change and the why.

## Conventions

- PHP `^8.4.1`, Laravel `^13`. `declare(strict_types=1)` everywhere.
- Code style: Laravel Pint preset (`composer format` to apply).
- Static analysis: PHPStan/Larastan level 8 must pass.
- Keep the engine **headless** — no web/UI coupling belongs in this package
  (it goes in `laranail/installer-web`).
- All step logic lives in `Step` classes driven by `InstallerEngine`; front ends
  only collect input and call the engine.
- Artisan commands use the `laranail::installer.<command>` naming shape.

## Tests

Feature tests drive the engine on an in-memory/file SQLite database. Add unit
tests for support classes and feature tests for steps/commands.
