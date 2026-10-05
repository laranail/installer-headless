# Changelog

All notable changes to `laranail/installer-headless` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **`docs/tools/security.md` signs links with the vendor-scoped route name.** The expiring-link
  example used `installer-web.index`, which `laranail/installer-web` now serves only as a
  deprecated alias; it now uses `laranail-installer-web.index` and notes the old name still
  resolves with a deprecation notice.
- `require` now declares every Illuminate component `src/` uses: `illuminate/config`,
  `illuminate/container`, `illuminate/encryption`, `illuminate/events`, `illuminate/hashing`,
  `illuminate/log`, `illuminate/notifications`, `illuminate/pipeline` and
  `illuminate/translation`, plus `laravel/framework ^13.0`, because the global helpers `src/`
  calls (`config()`, `app()`, `trans()`, `base_path()`, ...) are defined only in
  `Illuminate/Foundation/helpers.php`. They arrived only transitively before (some through
  `laranail/package-tools`, the rest only through Testbench), so a consumer on a slimmer stack
  could install without one. `tests/Unit/DeclaredRequirementsTest.php` scans `src/` and fails on
  any use the manifest does not declare.

## [0.1.0] - 2026-07-11

### Fixed

- **`RoleManager` validated a configured driver class *after* constructing it.** `createDriver()`
  accepts a class name as a driver — a deliberate seam, since `installer.user.role_driver` may name
  a custom `RoleDriver` FQCN — but it called `$container->make($driver)` first and checked
  `instanceof RoleDriver` second. A name that was not a `RoleDriver` therefore had its constructor
  run, along with any container binding it triggered, before being rejected.

  It now checks `is_a($driver, RoleDriver::class, true)`, which answers from the class definition
  without building anything. The post-construction check stays, because the container may be bound
  to return something else for that class name. Valid drivers, built-in names and `extend()`
  registrations are unaffected.

  `laranail/captcha`'s adapter factory already named this as its reason for resolving through an
  exhaustive `match` on an enum instead: acceptable for a value a developer writes, wrong for one
  arriving from a config file an operator edits and, in a multi-tenant install, from a database row.

### Added

- `tests/Feature/RoleDriverResolutionTest.php` — asserts a rejected class is never constructed, and
  that the class-name seam still resolves a genuine driver.

Initial public release.

[Unreleased]: https://github.com/laranail/installer-headless/compare/v0.1.0...HEAD
