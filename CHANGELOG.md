# Changelog

All notable changes to `laranail/installer-headless` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed (BREAKING — pre-release, no aliases)

- **`admin` → `user` generalization.** The user step is generic, not admin-assuming:
  `src/Admin/` → `src/Users/`, `AdminAccountCreator` → `UserAccountCreator`,
  `AdminStep` → `CreateUserStep` (step key `admin` → `user`), config
  `installer.admin.*` → `installer.user.*`, CLI `--admin-*` → `--user-*`, env
  `INSTALLER_ADMIN_ROLE` → `INSTALLER_USER_ROLE`. See `UPGRADING.md`.
- **No assumed admin role.** `user.role` defaults to `null` (assign nothing); opt into
  `user.first_user_is_admin` to make only the first user an admin (`user.admin_role`).

### Added

- **Flexible name shape** — `user.name_shape` = `single` (one `name`) or `split`
  (`first_name` + `last_name`); columns via `user.fields`. `UserData` carries both.
- **Runtime extensibility DSL** — the `Installer` facade + `InstallerManager`: register
  steps, per-step fields, user-creation hooks, per-step input pipelines, products and
  event listeners from a consumer provider with no fork. Concrete steps, `StepRegistry`
  and `InstallerEngine` are now non-final and `Macroable` (subclass / container
  `extend()` / macros). See `docs/tools/extending.md`.
- **Per-step extra fields** for ANY step (`StepFieldHooks`, config
  `installer.steps.<key>.fields` or `Installer::field()`); `Field::fromArray()`.
- **Per-step input pipelines** (`StepPipelines` / `Installer::pipe()`) run before
  validation (normalise / enrich / veto).
- **`RoleManager` is now an `Illuminate\Support\Manager`** — register custom role
  drivers at runtime via `RoleManager::extend()`; FQCN drivers still supported.
- **Field-driven CLI** — `install` collects exactly the active pipeline's fields
  (generic `--field=name=value` + friendly aliases), fixing over-prompting for light
  product pipelines.
- **Typed user steps** — `CreateUserStep` is per-instance configurable
  (`new CreateUserStep(key:'admin-user', role:'admin', label:'Admin')`) so consumers can
  register member/admin/superadmin steps; optional in-form role picker via
  `installer.user.role_field`; `UserAccountCreator::createMany()` for bulk creation.
- **`ImportUsersStep`** (`import-users`) — bulk user import from a CSV or array, idempotent
  by email; **`ImportDatabaseStep`** (`import-database`) — SQL-dump import via the optional
  `laranail/database-tools`. Both registered **disabled by default** (config- or
  DSL-toggleable) and declare a `path` field. `database-tools` added to `suggest`.

## [0.1.0] - 2026-06-27

### Added

- **Headless installer engine** decoupled from Botble — runs on any Laravel
  `^13` app with zero `botble/*` dependencies.
- **Single wizard engine** (`InstallerEngine` + `StepRegistry` + `Step` +
  `Wizard\Field`/`WizardValidator`): each step declares its `fields()` and `rules()`
  once — the single validation source both CLI and web reuse — with navigation
  (`current`/`next`/`previous`/`progress`), ordering guards, conditional field
  visibility, and per-step input persistence/resume (non-sensitive by default;
  encrypted via `wizard.persist_secrets`).
- **Pluggable step pipeline** with register/insert/reorder/disable/replace,
  config-driven enable/priority, events (`StepStarted`/`StepCompleted`/`StepFailed`)
  and resumable, idempotent runs.
- Default steps: welcome, requirements, environment, migrate, admin, license
  (off by default), final — plus a generic, working `ChoiceStep` (disabled by default).
- **Conservative post-install cleanup** (`PostInstallCleanup`, opt-in file list).
- **Live CLI install dashboard** — `laranail::installer.install` renders a progress
  bar over the steps via `laranail/console`'s `ProgressReporter` seam (laravel/prompts
  by default, graceful in CI; optional experimental `symfony/tui` full-screen renderer
  via `console.tui.progress`). Validation/install errors surfaced inline.
- **Per-role user forms** (`UserFormHooks`) — config- and runtime-driven extra
  user-form fields (flat or role-keyed `admin.form_fields`), rendered/validated via
  the wizard `Field` system and persisted as attributes via `UserData::$extra`.
  Complements `UserCreationHooks` (creation lifecycle) and supersedes the old
  `EnvironmentFieldResolver` declarative-field idea.
- **Per-product pipeline orchestrator** — `InstallerEngine::forProduct()` runs each
  product's **own pipeline** (its steps/order/priorities/config), not just isolated
  state. Config-driven (`installer.products.<slug>`, with optional `InstallType`
  presets) or runtime-registered via `ProductRegistry`/`ProductPipeline`; per-product
  config reaches steps via `InstallerContext::productConfig()` (and an opt-in global
  `config_overlay`). Selected steps run even if globally disabled; single-product is
  the degenerate case (no breaking changes). CLI `--product=<slug>` / `--all-products`;
  `StepRegistry::select()` added.
- **Multi-product state isolation** — `InstallationState::forProduct()` namespaces
  markers/progress/resume per product.
- **`.env` engine** (`EnvFile`/`EnvWriter`): generate **and** update, preserving
  comments/order/formatting, phpdotenv-compatible quoting, atomic writes (`0600`).
- **Requirements checker**, **database connection test**, **migration/seeder runner**.
- **Admin creation** via a configurable model + field map (works on any schema)
  **and** a fully overridable creator; role assignment via auto-detected drivers
  (Spatie / Eloquent / Null).
- **License step** delegating to `laranail/license-verifier` (default off) with
  capability mapping (activate / revalidate / deactivate / transfer).
- **Multi-layer install state** + install-once lock with marker files and heuristics.
- **CLI**: `laranail::installer.install` (headless/interactive), `…​.status`,
  `…​.reset` (local-guarded), `…​.env` (format-preserving key update).
- **Structured, secret-masked logging** of lifecycle events.
- Tests (Pest), PHPStan level 8, Pint and Rector configuration.
