# Admin panels (Filament, Nova & others)

The installer runs **before** the app is accessible, so it ships **no** Filament/Nova
plugins and depends on neither. Instead it gives you the **seams** to make a panel
succeed: create an admin user with the right role, then react to install events from
your own provider. Everything below lives in the **consumer** app.

## 1. Create a panel admin

Use a role driver (auto-detects Spatie, else an Eloquent `role` column; or register a
custom one) and either the first-user-is-admin policy or a typed admin step.

```dotenv
INSTALLER_USER_ROLE=                 # default role (blank = none)
INSTALLER_FIRST_USER_ADMIN=true      # make the first user an admin
INSTALLER_USER_ADMIN_ROLE=admin
```

Or register a typed admin step from your provider's `boot()`:

```php
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\Steps\CreateUserStep;

Installer::step(new CreateUserStep(key: 'admin-user', role: 'admin', label: 'Admin'));
```

A custom role system:

```php
app(\Simtabi\Laranail\Installer\Headless\Users\RoleManager::class)
    ->extend('acme', fn ($app) => new \App\Installer\AcmeRoleDriver);
// installer.user.role_driver=acme
```

## 2. Grant / gate panel access

React to `UserCreated` (or `InstallerFinished`) — guard panel calls with
`class_exists` so this is a no-op until the panel is installed.

```php
use Simtabi\Laranail\Installer\Headless\Events\UserCreated;
use Simtabi\Laranail\Installer\Headless\Facades\Installer;

Installer::listen(UserCreated::class, function (UserCreated $e): void {
    // e.g. flag the user for panel access, send a welcome mail, etc.
});
```

**Filament** — gate access on your User model:

```php
public function canAccessPanel(\Filament\Panel $panel): bool
{
    return $this->hasRole('admin'); // Spatie, or your role column
}
```

**Nova** — in your `NovaServiceProvider`:

```php
protected function gate(): void
{
    \Illuminate\Support\Facades\Gate::define('viewNova', fn ($user) => $user->hasRole('admin'));
}
```

## 3. Seed panel resources / run panel setup

Hook `StepCompleted` / `InstallerFinished` to do panel-specific setup once the app is
installed (guarded):

```php
use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Facades\Installer;

Installer::listen(InstallerFinished::class, function (): void {
    if (class_exists(\Filament\Panel::class)) {
        \Illuminate\Support\Facades\Artisan::call('filament:upgrade');
        // seed panel resources, shield roles, etc.
    }
});
```

## 4. Test it

```php
Installer::fake();
// run the installer...
Installer::fake()->assertUserCreated(fn ($e) => $e->data->role === 'admin');
```

The same pattern works for any post-install tool (search indexers, billing, etc.):
create what you need, then react to the installer's events — no installer dependency on
the tool.

[← Docs index](../../README.md#documentation)
