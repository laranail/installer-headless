<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Users\RoleManager;
use Simtabi\Laranail\Installer\Headless\Contracts\RoleDriver;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;

/**
 * `RoleManager::createDriver()` accepts a **class name** as a driver, which `Illuminate\Support\Manager`
 * does not: the name from `installer.user.role_driver` is passed to the container when it happens to
 * name a real class.
 *
 * That is a deliberate and useful seam, but it used to build the object *before* checking the type —
 * so a name that is not a `RoleDriver` still had its constructor run, along with any container
 * binding it triggered, before being rejected. `laranail/captcha`'s adapter factory calls this out
 * as the reason it resolves through an exhaustive `match` on an enum instead: fine for a value a
 * developer writes, wrong for one that arrives from a config file an operator edits and, in a
 * multi-tenant install, from a database row.
 *
 * `is_a($class, $interface, true)` answers from the class definition without constructing anything.
 */
it('refuses a class that is not a RoleDriver', function (): void {
    config()->set('installer.user.role_driver', NotARoleDriver::class);

    expect(fn () => app(RoleManager::class)->resolve())->toThrow(InstallerException::class);
});

it('does not construct a rejected class', function (): void {
    // The assertion that distinguishes check-then-build from build-then-check. Before the fix this
    // counter reached 1 and the test below failed.
    NotARoleDriver::$constructed = 0;
    config()->set('installer.user.role_driver', NotARoleDriver::class);

    try {
        app(RoleManager::class)->resolve();
    } catch (InstallerException) {
        // expected
    }

    expect(NotARoleDriver::$constructed)->toBe(
        0,
        'the rejected class was instantiated before its type was checked',
    );
});

it('still resolves a genuine RoleDriver given by class name', function (): void {
    // The seam has to keep working: rejecting early must not reject the valid case.
    config()->set('installer.user.role_driver', AcceptableRoleDriver::class);

    expect(app(RoleManager::class)->resolve())->toBeInstanceOf(AcceptableRoleDriver::class);
});

it('still resolves the built-in drivers by plain name', function (): void {
    config()->set('installer.user.role_driver', 'null');

    expect(app(RoleManager::class)->resolve())->toBeInstanceOf(RoleDriver::class);
});

final class NotARoleDriver
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }
}

final class AcceptableRoleDriver implements RoleDriver
{
    public function assign(object $user, string $role): void {}
}
