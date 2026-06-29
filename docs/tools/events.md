# Events, notifications & testing

The installer emits lifecycle events you can react to, ships an optional notification
layer, and provides a test fake — all without editing package source.

## Events

All live in `Simtabi\Laranail\Installer\Headless\Events` and are plain `Dispatchable`
classes with secret-free payloads.

| Event | Fired when | Payload |
| --- | --- | --- |
| `InstallerStarted` | the install begins (first time the installing marker is set) | `?string $product` |
| `StepStarted` | a step begins | `string $step` |
| `StepCompleted` | a step succeeds | `string $step, array $context` |
| `StepFailed` | a step throws | `string $step, Throwable $exception` |
| `EnvironmentSaved` | `.env` is written (values pre-masked) | `array $values` |
| `UserCreated` | a user is created (incl. each import row) | `object $user, UserData $data` |
| `InstallerFailed` | a step throws and halts the run (top-level) | `?string $step, Throwable $exception` |
| `UnauthorizedInstallerAccess` | an access control denies a request | `string $reason, ?string $ip, string $path` |
| `InstallerFinished` | install completes and the lock is set | — |

Listen from your provider's `boot()` via the DSL (or a normal `Event::listen`):

```php
use Simtabi\Laranail\Installer\Headless\Events\UserCreated;
use Simtabi\Laranail\Installer\Headless\Facades\Installer;

Installer::listen(UserCreated::class, function (UserCreated $e): void {
    // e.g. grant the first user access to your Filament/Nova panel
});
```

Every event is logged (secret-masked) to `installer.logging.channel`.

## Notifications

Off by default. Enable and list recipients:

```dotenv
INSTALLER_NOTIFICATIONS=true
INSTALLER_NOTIFY_EMAILS="ops@example.com"
INSTALLER_NOTIFY_CHANNELS="mail"          # add slack,database,…
```

- `InstallationCompleted` — on `InstallerFinished`.
- `InstallationFailed` — on the top-level `InstallerFailed`.
- `UnauthorizedAccessAlert` — security stream, enabled separately:

```dotenv
INSTALLER_SECURITY_ALERTS=true
INSTALLER_SECURITY_ALERT_EMAILS="security@example.com"
```

Channels come from `notifications.channels`; the `mail` channel routes to
`notifications.mail.to`, other channels to `notifications.routes.<channel>` (e.g. a
Slack webhook URL). Sends are **synchronous** (no queue worker needed — safe on shared
hosting) and wrapped so a failing transport never breaks the install.

## Testing — `Installer::fake()`

Assert against the installer in your own tests without hand-rolling
`Event::assertDispatched`:

```php
use Simtabi\Laranail\Installer\Headless\Facades\Installer;

$fake = Installer::fake();

// ... run the installer / hit the wizard ...

$fake->assertStarted()
    ->assertStepCompleted('environment')
    ->assertUserCreated(fn ($e) => $e->data->email === 'ada@example.com')
    ->assertFinished();

// security:
$fake->assertUnauthorized('ip');
// failure path:
$fake->assertFailed()->assertNotFinished();
```

[← Docs index](../../README.md#documentation)
