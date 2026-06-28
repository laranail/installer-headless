# Extending the installer at runtime

The installer is reshapeable from your own service provider's `boot()` — no fork, no
edits to package source. A single fluent entry point, the `Installer` facade, drives
the underlying registries; everything is opt-in and the defaults work headless.

```php
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;
use Simtabi\Laranail\Installer\Headless\Events\StepCompleted;

public function boot(): void
{
    Installer::step(new TermsStep)                       // register / replace a step
        ->before('user', new ProfileStep)               // insert relative to another
        ->field('environment', new Field('region', 'Region', 'select', 'eu', options: ['eu' => 'EU', 'us' => 'US']))
        ->pipe('user', NormaliseEmail::class)            // per-step input transform (pre-validation)
        ->creating(fn ($data) => null)                   // user-creation lifecycle hook
        ->product('addon', ['type' => 'module'])         // a product pipeline
        ->listen(StepCompleted::class, fn ($e) => logger($e->step));
}
```

Every method returns the manager, so it reads as one chain. Each is backed by a
shared container singleton, so order across providers is predictable.

## Steps

| DSL | Effect |
|---|---|
| `Installer::step($step)` | register (replaces a same-keyed step) |
| `Installer::before('key', $step)` / `after('key', $step)` | insert relative to a step |
| `Installer::removeStep('key')` | drop a step |

Concrete steps, `StepRegistry` and `InstallerEngine` are **not final**, so you can
also subclass a shipped step and register the subclass, or decorate a step through
the container:

```php
$this->app->extend(WelcomeStep::class, fn ($step, $app) => new LoggingWelcomeStep($step));
```

## Extra fields on any step

Add fields to **any** step (rendered + validated + persisted through the one wizard
`Field` path) — via config or the DSL:

```php
// config/installer.php — installer.steps.<key>.fields
'steps' => ['welcome' => ['fields' => [
    ['name' => 'newsletter', 'label' => 'Subscribe', 'type' => 'checkbox'],
]]],
```
```php
Installer::field('welcome', new Field('newsletter', 'Subscribe', 'checkbox'));
```

The user step additionally honours role-keyed `installer.user.form_fields`
(`UserFormHooks`) and reserves its core fields (`name`/`email`/`password`/…), so an
extra field can never shadow them.

## Per-step input pipelines

A step's collected input runs through its registered stages (an
`Illuminate\Pipeline\Pipeline`) **before validation** — normalise, enrich, or veto:

```php
Installer::pipe('user', fn (array $input, Closure $next) => $next([
    ...$input,
    'email' => strtolower($input['email'] ?? ''),
]));
```

A stage is a closure `(array, Closure): array` or a class with
`handle(array $input, Closure $next): array`; not calling `$next` short-circuits.

## User creation & roles

User creation is generic (see [configuration.md](../configuration.md)) — hooks via
`Installer::preparing/creating/roleAssigning/created`. Role assignment is a
`Illuminate\Support\Manager`, so register a custom role driver at runtime:

```php
app(\Simtabi\Laranail\Installer\Headless\Users\RoleManager::class)
    ->extend('acme', fn ($app) => new AcmeRoleDriver);
// then config('installer.user.role_driver') = 'acme'
```

## Macros

`AbstractStep`, `StepRegistry` and `InstallerEngine` are `Macroable`:

```php
InstallerEngine::macro('remainingSteps', fn () => array_slice($this->orderedSteps(), 1));
```

## Products

See [steps.md](steps.md#multi-product-installs-per-product-pipeline-orchestrator)
for per-product pipelines; `Installer::product($slug, $definition)` registers one at
runtime.

## Notes

- Register closures (custom steps, hooks, pipeline stages) in `boot()` via the DSL —
  **never** store closures in `config()` (it breaks `config:cache`).
- The web UI consumes the same registries; anything you register here applies to both
  the CLI and the web wizard.

[← Docs index](../../README.md#documentation)
