# Configuration

All keys live under the flat `installer.*` namespace (`config/installer.php`).

| Key | Default | Purpose |
|---|---|---|
| `enabled` | `true` | Master switch; when false the app is treated as installed. |
| `redirect_to` | `'/'` | Post-install redirect / "already installed" target. |
| `env.path` | `null` → `base_path('.env')` | Where the `.env` is written/read. |
| `env.example` | `null` → `base_path('.env.example')` | Template for generation. |
| `locales` | `['en' => 'English']` | Welcome-step language options. |
| `requirements.php` | `'8.4.1'` | Minimum PHP version. |
| `requirements.extensions` | list | Hard-required PHP extensions. |
| `requirements.optional` | list | Reported but non-blocking. |
| `requirements.apache` | `['mod_rewrite']` | Apache modules (best-effort). |
| `requirements.permissions` | paths | Paths that must be writable. |
| `database.seeder` | `null` | Optional seeder class run after migrate. |
| `database.import.enabled` | `false` | Optional SQL-dump import (via `database-tools`). |
| `database.import.path` | `null` | SQL dump path for the `import-database` step. |
| `database.import.connection` | `null` | Connection the dump restores onto (null = default). |
| `users.import.path` | `null` | CSV path for the `import-users` step (header row → columns). |
| `users.import.rows` | `[]` | Inline user rows for `import-users` (instead of a CSV). |
| `user.model` | `App\Models\User` | Eloquent user model. |
| `user.name_shape` | `'single'` | `single` (one `name`) or `split` (`first_name`+`last_name`). |
| `user.fields` | `name/first_name/last_name/email/password` map | Map logical fields → your columns. |
| `user.attributes` | `[]` | Extra attributes set on create. |
| `user.role_driver` | `null` (auto) | `null` \| `spatie` \| `eloquent` \| `null` \| FQCN (driver via `RoleManager::extend()`). |
| `user.role` | `null` | Role to assign (generic — **no** assumed admin); null = assign nothing. |
| `user.type` | `null` | Optional user-type label for your own logic. |
| `user.first_user_is_admin` | `false` | Opt-in: assign `user.admin_role` to the first user only. |
| `user.admin_role` | `'admin'` | The role used when `first_user_is_admin` applies. |
| `user.creator` | `null` | Callable/FQCN that fully overrides creation. |
| `user.role_field` | `[]` | `[value => label]` — adds an in-form role picker to the user step. |
| `user.form_fields` | `[]` | Extra user-form fields (flat list, or role-keyed `['admin'=>[…], '*'=>[…]]`), resolved by `UserFormHooks`. |
| `steps.<key>.fields` | `[]` | Extra fields for **any** step (resolved by `StepFieldHooks`). |
| `steps.<key>.icon` / `.description` | `null` | Optional nav icon/description (shown by the web wizard). |
| `license.enabled` | `false` | Turn on the license step. |
| `license.skippable` | `true` | Allow skipping the license step. |
| `steps.<key>.enabled` | per step | Toggle a step. |
| `steps.<key>.priority` | per step | Reorder a step. |
| `steps.choice.options` | `[]` | Options for the generic choice step. |
| `lock.installing` / `lock.installed` | marker names | Marker files under `storage_path()`. |
| `lock.timeout` | `30` | Minutes before a stale in-progress install is abandoned. |
| `logging.channel` | `'stack'` | Channel for structured installer logging. |
| `wizard.persist_input` | `true` | Persist collected step input for resume / back-forward re-hydration. |
| `wizard.persist_secrets` | `false` | When true, sensitive fields are persisted **encrypted**; otherwise dropped and re-entered. |
| `cleanup.files` | `[]` | Files (relative to `base_path`) deleted by the final step (e.g. a bundled `database.sql`). Empty = nothing removed. |
| `notifications.enabled` | `false` | Email install completion/failure to the recipients below. |
| `notifications.mail.to` | `[]` (env `INSTALLER_NOTIFY_EMAILS`, comma-sep) | On-demand mail recipients. |
| `products` | `[]` | Optional product slugs ⇒ metadata for multi-product installs (`InstallerEngine::forProduct()` gives each isolated state/progress). |

## User creation — generic, not "admin"

The installer creates a **user**, not an assumed admin. Set `user.role` to assign a
role, or opt into `user.first_user_is_admin` to make only the very first user an admin
(`user.admin_role`). Use `user.name_shape = split` for `first_name`+`last_name` models.

For finer control than the model/field-map config or the full `user.creator`
override, register `UserCreationHooks` callbacks from your provider's `boot()`:

```php
app(\Simtabi\Laranail\Installer\Headless\Users\UserCreationHooks::class)
    ->preparing(fn (array $attrs, $data) => $attrs + ['tenant_id' => 1])
    ->creating(fn ($data) => null)                 // return a user to fully override creation
    ->roleAssigning(fn ($user, $role) => false)    // return true to take over role assignment
    ->created(fn ($user, $data) => activity()->log('user created'));
```

## Per-role user forms (`UserFormHooks`)

`UserCreationHooks` owns the *creation* lifecycle; `UserFormHooks` shapes the *form* —
the extra fields a given role's user gets, rendered + validated + persisted as
attributes. Both build on the wizard `Field` system, so extra fields work in the CLI
and the web UI from one definition (the declarative-field need the old
`EnvironmentFieldResolver` covered is met the same way).

Config-driven (flat list, or role-keyed) via `user.form_fields`:

```php
'user' => [
    'form_fields' => [
        '*'     => [['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string']]],
        'admin' => [['name' => 'department', 'label' => 'Department', 'rules' => ['required', 'string']]],
    ],
],
```

…or at runtime via the `Installer` DSL (works for **any** step — see
[tools/extending.md](tools/extending.md)):

```php
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

Installer::field('user', new Field('company', 'Company', 'text', '', ['required', 'string', 'max:120']));
```

Collected values land in `UserData::$extra` and persist as attributes (combine with a
`UserCreationHooks::preparing` hook to transform them). For provisioning **multiple**
users, register a custom step per user that calls `UserAccountCreator` — the form and
creation hooks compose without a separate multi-user engine.

Database connection is configured at runtime from the environment step; you do
not pre-configure credentials in this file.

## Access lockdown — `installer.security.*`

Restrict who/when/how fast the web installer is reached (IP allowlist, host allowlist,
HTTPS, token/password gate, availability window, throttling, response headers,
auto-disable). All off by default. Full reference: [tools/security.md](tools/security.md).

## Notifications — `installer.notifications.*`

`enabled`, `channels` (default `mail`), `mail.to`, per-channel `routes`, and a separate
`security` alert stream. See [tools/events.md](tools/events.md).

## Hosting environment — `installer.environment.*`

`mode` (auto|shared|vps), `session_store`/`cache_store` (forced for installer requests),
and `time_limit`. See [tools/shared-hosting.md](tools/shared-hosting.md).

## See also

- [tools/steps.md](tools/steps.md) — the step pipeline.
- [tools/license.md](tools/license.md) — license configuration.

[← Docs index](../README.md#documentation)
