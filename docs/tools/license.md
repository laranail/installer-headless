# License verification

License verification is **off by default** (open-source / self-hosted friendly).
When enabled, the installer's license step delegates to
[`laranail/license-verifier`](https://opensource.simtabi.com/license-verifier/) —
the installer never references a concrete driver.

## Enable

```php
// config/installer.php
'license' => ['enabled' => true, 'skippable' => true],
'steps'   => ['license' => ['enabled' => true]],
```

Then choose a driver in license-verifier's config:

```php
// config/license-verifier.php
'default' => 'envato', // or keygen, lemonsqueezy, gumroad, paddle, null, …
```

Set `default => 'null'` for an "enabled but always passes" mode (e.g. internal
builds): the bundled `NullDriver` returns a valid result.

## Capability mapping

The installer step only activates; the four capabilities map onto license-verifier:

| Capability | Maps to |
|---|---|
| activate | `LicenseStepAdapter::activate()` → `LicenseManager::activate()` |
| revalidate | `LicenseManager::verify()` |
| deactivate | `LicenseManager::deactivate()` |
| transfer (domain move) | the active driver implementing `SupportsDomainBinding` |

Capability availability is `instanceof`-gated (`LicenseStepAdapter::supportsTransfer()`).
Revalidate/deactivate/transfer are exposed through license-verifier's own
commands — not duplicated here.

## Caching, grace & custom drivers

Caching, offline grace and custom drivers/decorators are configured at the
license-verifier layer (its `cache` / `storage.fallback` config and
`DriverManager::extend()`), not in the installer.

[← Docs index](../../README.md#documentation)
