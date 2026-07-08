# Extending the step pipeline

Steps live in a `StepRegistry` resolved from the container. Each `Step` exposes
`key()`, `label()`, `priority()`, `isEnabled()` and `run(InstallerContext)`.

## Default pipeline

`welcome` (10) → `requirements` (20) → `environment` (30) → `migrate` (40) →
`user` (50) → `license` (60, off by default) → `final` (70). The generic
`choice` step (35) ships disabled.

## Enable / disable / reorder (config only)

```php
// config/installer.php
'steps' => [
    'license' => ['enabled' => true, 'priority' => 60],
    'choice'  => ['enabled' => true, 'priority' => 35, 'options' => [...]],
],
```

## Register / insert / replace / remove (runtime)

From your own service provider's `boot()`:

```php
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;

$registry = app(StepRegistry::class);
$registry->register(new MyStep);                 // add (replaces same key)
$registry->before('user', new MyStep);          // insert before
$registry->after('environment', new MyStep);     // insert after
$registry->remove('welcome');                    // drop
```

## The generic `ChoiceStep`

A working single-select step — the no-code way to re-add theme/preset choice:

```php
'steps' => [
    'choice' => [
        'enabled' => true,
        'priority' => 35,
        'options' => [
            'classic' => ['label' => 'Classic', 'callback' => function (string $value, $context): void {
                // e.g. import a per-theme SQL dump via laranail/db-tools
            }],
            'modern' => ['label' => 'Modern'],
        ],
    ],
],
```

The selection is validated, persisted (`InstallationState::recall('choice')`),
the optional per-option callback runs, and a `StepCompleted` event fires. For
richer UX, write a custom `Step` and register it instead.

## Writing a custom step

```php
final class BackupStep extends AbstractStep
{
    protected string $key = 'backup';
    protected int $defaultPriority = 45;

    // Declare input once — rules() and rendering derive from these fields.
    // Override stepFields() (not fields()) so consumer-registered extra fields
    // (StepFieldHooks / Installer::field()) still merge in.
    protected function stepFields(): array
    {
        return [
            new Field('backup_path', 'Backup path', 'text', storage_path('backup'), ['required', 'string']),
        ];
    }

    public function run(InstallerContext $context): void
    {
        $path = $context->input('backup_path');
        // ...
    }
}
```

Because the step declares its `fields()`/`rules()` in core, it works in **both**
the CLI (prompts + the same validation) and the web UI (the generic Livewire
`WizardStep` renders the fields and validates against the same rules) with no
extra code — a single definition drives both front ends. Field-less steps simply
omit `fields()`.

## Multi-product installs (per-product pipeline orchestrator)

Each product can run its **own pipeline** — its own steps, order, priorities and
config — while sharing one engine. Single-product is just the degenerate case (no
products configured ⇒ the default pipeline, unchanged).

### Declare products (config)

```php
// config/installer.php
'products' => [
    'core'    => ['type' => 'app'],                                  // full default pipeline
    'addon-x' => [
        'type'   => 'module',                                        // preset: requirements→migrate→license→final
        'steps'  => ['requirements', 'migrate', 'license', 'final'], // explicit order (overrides the preset)
        'config' => ['database' => ['seeder' => \Database\Seeders\AddonXSeeder::class]],
    ],
],
```

- `steps` is an **ordered** list and *is* the enabled set — a listed step runs even
  if globally disabled (e.g. `license`). Empty `steps` (or just a `type`) = the
  preset/default pipeline.
- `priorities` overrides order for specific keys; otherwise order follows the list.
- `type` (`app`/`module`/`plugin`) is an optional preset; explicit `steps` always wins.

### Run a product

```php
$engine = app(InstallerEngine::class);
$engine->forProduct('core')->run($context);     // core's pipeline + markers installer.core.*
$engine->forProduct('addon-x')->run($context);  // addon-x's pipeline + markers installer.addon-x.* (isolated)
```

CLI: `php artisan laranail::installer.install --product=addon-x` (one) or
`--all-products` (every registered product, each with isolated state). No flag → the
default pipeline. Web: `/install/p/{product}/{step}` routes the wizard through
`forProduct()` (the default `/install/{step}` routes are unchanged).

### Register products at runtime

```php
use Simtabi\Laranail\Installer\Headless\Support\{ProductRegistry, ProductPipeline};

app(ProductRegistry::class)->register(new ProductPipeline(
    slug: 'addon-y',
    steps: ['requirements', 'final'],
    config: ['foo' => 'bar'],
));
```

### Reading per-product config from a step

```php
public function run(InstallerContext $context): void
{
    $seeder = $context->productConfig('database.seeder');  // from the product's `config`
    $slug   = $context->product()?->slug;
}
```

Set `'config_overlay' => true` on a product to also merge its `config` into global
`installer.*` during that product's run (handy for existing `config()`-reading steps;
restored afterwards — best for CLI, use the context accessor under web/Octane).

## Typed user steps (member / admin / superadmin)

`CreateUserStep` is per-instance configurable, so register as many typed user steps as
you need — each with its own key, role and label:

```php
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\Steps\CreateUserStep;

Installer::step(new CreateUserStep(key: 'admin-user', role: 'admin', label: 'Administrator'))
    ->after('admin-user', new CreateUserStep(key: 'member-user', role: 'member', label: 'Member'));
```

The instance `role` seeds `UserData::$role` (assigned via the role driver); the created
user is stored on the context under the step key. The shipped default step
(`key: 'user'`) is unchanged. To let the operator **pick** a role in the form instead,
set `installer.user.role_field` to `[value => label]` — a core role `select` is added to
the user step and flows to `UserData::$role`.

Bulk creation reuses the same lifecycle: `app(UserAccountCreator::class)->createMany($users)`.

## Bulk user import (`import-users`) and DB import (`import-database`)

Two optional steps ship **registered but disabled** — enable via config
(`installer.steps.import-users.enabled` / `installer.steps.import-database.enabled`) or
`Installer::step(...)`:

- **`import-users`** — bulk-creates users from a CSV (`installer.users.import.path`, header
  row → columns) or an inline array (`installer.users.import.rows`) via `createMany()`,
  idempotent by email. **Security:** a CSV with plaintext passwords is sensitive — add it
  to `installer.cleanup.files` so the final step removes it; import logs stay secret-masked.
- **`import-database`** — restores a SQL dump via the optional `laranail/db-tools`
  (`SqlFileRestorer`), driven by `installer.database.import.{path,connection}`. Install
  `laranail/db-tools` to use it; otherwise the step errors clearly when run.

Both declare a `path` field, so they render in the web wizard and accept a `--field=path=…`
override on the CLI.

[← Docs index](../../README.md#documentation)
