# Architecture

## Two-package boundary

```
laranail/installer-web  ──depends on──▶  laranail/installer-headless
   (Livewire 4 + Tailwind UI)               (this package: all logic)
```

The dependency is strictly one-directional. The headless engine never knows the
web layer exists; any front end (web, CLI, API) drives it through the same
public surface and may submit identical input arrays.

## The wizard engine (single source for both front ends)

`InstallerEngine` is the one wizard engine — there is no second notion of "steps".
It owns ordering, navigation, ordering guards, validation, per-step input
persistence/resume, events and the install lock. See [tools/wizard.md](tools/wizard.md).

- **Single validation path.** Each `Step` declares its input `fields()` (a
  `Wizard\Field` VO) and `rules()` (plain Laravel rule arrays) once. The engine's
  `WizardValidator` runs those rules — and exposes the exact same rules to front
  ends (`InstallerEngine::rules()`). The web layer reuses them verbatim and
  declares **none** of its own.
- **Navigation & guards.** `current()`, `next()`, `previous()`, `progress()`, and
  `canAccess()` (a step can't run until earlier steps complete).
- **Persistence & resume.** `submit()`/`runStep()` persist collected input via
  `InstallationState` (non-sensitive by default; encrypted when
  `wizard.persist_secrets`), so a refresh/crash/back-forward re-hydrates.

## Public API

- **`InstallerEngine`** — `run(InstallerContext, resume)`, `runStep(key, context)`,
  `submit(key, input)`, `fields(key)`, `values(key)`, `rules(key, input)`,
  `current()/next()/previous()/progress()/canAccess()`, `steps()`.
- **`StepRegistry` + `Step` + `Wizard\Field`** — the ordered, mutable pipeline
  (see [tools/steps.md](tools/steps.md)).
- **`InstallerContext`** — input/options/data carried across steps.
- **Support services** — `EnvFile`/`EnvWriter`, `RequirementsChecker`,
  `DatabaseConnection`, `MigrationRunner`, `InstallationState`,
  `SensitiveFieldDetector`, `PostInstallCleanup`, `WizardValidator`,
  `StepFieldHooks`, `StepPipelines`, `ProductRegistry`/`ProductPipeline`.
- **Users** (`Users\`) — `UserData`, `UserAccountCreator`, `UserCreationHooks`,
  `UserFormHooks`, `RoleManager` (an `Illuminate\Support\Manager`) + `RoleDriver`s.
  Generic: any user type/role, `first_user_is_admin` opt-in, single/split name shape.
- **Runtime DSL** — `InstallerManager` + the `Installer` facade: a consumer reshapes
  the installer (steps, fields, hooks, per-step pipelines, products, listeners) from
  their own provider's `boot()` with no fork (see [tools/extending.md](tools/extending.md)).
- **Per-product orchestrator** — `InstallerEngine::forProduct()` runs a product's own
  step pipeline with isolated state (`installer.products.<slug>` / `ProductRegistry`).
- **Licensing** — `LicenseStepAdapter` over `laranail/license-verifier`.
- **Events** — `StepStarted`/`StepCompleted`/`StepFailed`, `EnvironmentSaved`,
  `InstallerFinished`.

Concrete steps, `StepRegistry` and `InstallerEngine` are non-final and `Macroable`,
so consumers can subclass, decorate (container `extend()`), or add methods at runtime.

## Step lifecycle

Each `Step::run(InstallerContext)` is the single entry point both web and CLI
invoke — there is no separate "action" layer, so behavior cannot drift between
front ends. Per step the engine: enforces the ordering guard → validates input
(single path) → persists input → runs → marks complete (emitting events). The
final step runs cleanup and sets the install-once lock.

## Install detection

`InstallationState` is layered and conservative: a disabled installer counts as
installed; an explicit installed-marker wins; an in-progress marker means
"installing"; otherwise it falls back to heuristics (live DB + populated
migrations table + `APP_KEY`) so the guard holds even if a marker is deleted.

[← Docs index](../README.md#documentation)
