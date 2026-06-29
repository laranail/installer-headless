<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Testing;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Installer\Headless\Events\EnvironmentSaved;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFailed;
use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Events\InstallerStarted;
use Simtabi\Laranail\Installer\Headless\Events\StepCompleted;
use Simtabi\Laranail\Installer\Headless\Events\StepFailed;
use Simtabi\Laranail\Installer\Headless\Events\StepStarted;
use Simtabi\Laranail\Installer\Headless\Events\UnauthorizedInstallerAccess;
use Simtabi\Laranail\Installer\Headless\Events\UserCreated;

/**
 * Test double returned by InstallerManager::fake(). Built on Event::fake() over the
 * installer's lifecycle events, with fluent installer-specific assertions so consumers
 * can verify installer behaviour without hand-rolling Event::assertDispatched calls.
 */
final class InstallerFake
{
    /** @var list<class-string> */
    public const array EVENTS = [
        InstallerStarted::class,
        StepStarted::class,
        StepCompleted::class,
        StepFailed::class,
        EnvironmentSaved::class,
        UserCreated::class,
        InstallerFailed::class,
        UnauthorizedInstallerAccess::class,
        InstallerFinished::class,
    ];

    public function assertStarted(): self
    {
        Event::assertDispatched(InstallerStarted::class);

        return $this;
    }

    public function assertStepCompleted(string $key): self
    {
        Event::assertDispatched(StepCompleted::class, fn (StepCompleted $e): bool => $e->step === $key);

        return $this;
    }

    public function assertUserCreated(?callable $filter = null): self
    {
        Event::assertDispatched(UserCreated::class, $filter);

        return $this;
    }

    public function assertFinished(): self
    {
        Event::assertDispatched(InstallerFinished::class);

        return $this;
    }

    public function assertNotFinished(): self
    {
        Event::assertNotDispatched(InstallerFinished::class);

        return $this;
    }

    public function assertFailed(): self
    {
        Event::assertDispatched(InstallerFailed::class);

        return $this;
    }

    public function assertUnauthorized(?string $reason = null): self
    {
        Event::assertDispatched(
            UnauthorizedInstallerAccess::class,
            $reason === null ? null : fn (UnauthorizedInstallerAccess $e): bool => $e->reason === $reason,
        );

        return $this;
    }
}
