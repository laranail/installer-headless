# Upgrading

This package follows [Semantic Versioning](https://semver.org). Breaking changes
and their migration steps are documented here per major/minor version.

## Unreleased — `admin` → `user` (clean break)

The single-admin assumption was generalized to a configurable **user**. As the
package is pre-release, this is a clean break (no aliases). Migrate as follows:

| Old | New |
|---|---|
| config `installer.admin.*` | `installer.user.*` |
| step key `admin` | `user` |
| `installer.steps.admin` | `installer.steps.user` |
| CLI `--admin-name` / `--admin-email` / `--admin-password` | `--user-name` / `--user-email` / `--user-password` |
| env `INSTALLER_ADMIN_ROLE` | `INSTALLER_USER_ROLE` |
| `…\Headless\Admin\AdminAccountCreator` | `…\Headless\Users\UserAccountCreator` |
| `…\Headless\Admin\{UserData,RoleManager,UserCreationHooks,UserFormHooks,RoleDrivers\*}` | `…\Headless\Users\…` |
| `…\Headless\Steps\AdminStep` | `…\Headless\Steps\CreateUserStep` |

Behavioural changes:

- **No assumed admin role.** `installer.user.role` now defaults to `null` (assign
  nothing). To restore the old behaviour set `installer.user.role = 'admin'`, or opt
  into `installer.user.first_user_is_admin = true` (assigns `user.admin_role` to the
  first user only).
- **`RoleManager` is now an `Illuminate\Support\Manager`** — resolve it from the
  container (`app(RoleManager::class)`), not `new RoleManager`. `resolve()` still
  returns the `RoleDriver`; register custom drivers via `RoleManager::extend()`.
- **Custom steps** should override `protected stepFields()` (not `fields()`) so
  consumer-registered extra fields (`StepFieldHooks` / `Installer::field()`) merge in.

New (additive): `installer.user.name_shape` (`single`|`split`), the `Installer` facade
DSL, per-step `installer.steps.<key>.fields`, per-step input pipelines. See
`docs/tools/extending.md`.

## 0.1.0

Initial release — nothing to upgrade from.
