# Changelog

All notable changes to `laranail/installer-headless` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
