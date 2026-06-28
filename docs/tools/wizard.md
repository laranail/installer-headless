# The wizard engine

`InstallerEngine` is the single multi-step wizard both the CLI/TUI and the web UI
drive. All reusable logic — steps, fields, validation, state, persistence,
navigation, guards — lives here; the web package is a thin adapter.

## One definition, two front ends

A step declares its input once:

```php
public function fields(): array
{
    return [
        new Field('email', 'Email', 'email', '', ['required', 'email']),
        new Field('password', 'Password', 'password', '', ['required', 'min:8', 'confirmed'], sensitive: true),
        new Field('password_confirmation', 'Confirm', 'password', '', sensitive: true),
    ];
}
```

`rules()` is derived from the **visible** fields (conditional `visibleWhen` rules
drop hidden fields). That single `rules()` is what the engine validates against
**and** what the web FormRequest / Livewire component reuse — webui declares no
rules of its own.

## Engine API

| Method | Purpose |
|---|---|
| `fields($key)` | The step's `Field` list (for rendering). |
| `values($key)` | Defaults re-hydrated with persisted input. |
| `rules($key, $input)` | The step's rules for the current input (single source). |
| `submit($key, $input)` | Guard → validate → persist → run → return next key. |
| `runStep($key, $context)` | Same, from an `InstallerContext`. |
| `run($context, $resume)` | Run the whole pipeline (headless/CI). |
| `current()` / `next()` / `previous()` | Navigation. |
| `progress()` | `['total', 'completed', 'current']`. |
| `canAccess($key)` | Ordering guard — later steps blocked until earlier complete. |

## Validation

The single path is `WizardValidator`, which runs the step's Laravel rule arrays
and throws Illuminate's `ValidationException` (so web/CLI surface per-field
errors). There is no validation anywhere in the web package.

## Persistence & resume

`submit()`/`runStep()` persist collected input via `InstallationState`. By default
only non-sensitive fields are stored (sensitive fields re-entered each visit); set
`installer.wizard.persist_secrets = true` to persist everything **encrypted at
rest**. On revisit, `values()` re-hydrates defaults with the persisted input.

## Conditional visibility

`Field::visibleWhen` (`in` / `not` / `equals` against another field) hides fields
and drops their rules — e.g. the database host/port/user/password fields disappear
for the `sqlite` driver.

[← Docs index](../../README.md#documentation)
