# laranail/installer-headless

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/installer-headless.svg)](https://packagist.org/packages/laranail/installer-headless)
[![Tests](https://github.com/laranail/installer-headless/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/installer-headless/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/installer-headless/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/installer-headless/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Headless installer engine for any Laravel app — requirements checks, `.env` generate/update, DB connection test, migrations/seeder, user creation, a pluggable step pipeline, an install-once lock, and a full CLI/TUI. All install logic, no UI coupling — it runs fully headless (CI, Docker, headless servers).

PHP `^8.4.1` on Laravel `^13`. The web UI ships separately as [`laranail/installer-web`](https://opensource.simtabi.com/documentation/laranail/installer-web/).

## Install

```bash
composer require laranail/installer-headless
```

## Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/installer-headless](https://opensource.simtabi.com/documentation/laranail/installer-headless/)** — getting started, the step pipeline, license verification, the CLI/TUI, the public API for front ends, and configuration.

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
