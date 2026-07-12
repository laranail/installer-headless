<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
 * Structured, secret-safe logging of installer lifecycle events. Sensitive
 * values are masked via {@see SensitiveFieldDetector} before they are written;
 * the .env values on {@see EnvironmentSaved} are already masked by the emitter.
 */
final readonly class InstallerEventLogger
{
    public function __construct(private SensitiveFieldDetector $sensitive) {}

    public function handleStepStarted(StepStarted $event): void
    {
        $this->log('step.started', ['step' => $event->step]);
    }

    public function handleStepCompleted(StepCompleted $event): void
    {
        $this->log('step.completed', ['step' => $event->step, 'context' => $this->sensitive->mask($event->context)]);
    }

    public function handleStepFailed(StepFailed $event): void
    {
        $this->log('step.failed', ['step' => $event->step, 'error' => $event->exception->getMessage()], 'error');
    }

    public function handleEnvironmentSaved(EnvironmentSaved $event): void
    {
        $this->log('environment.saved', ['values' => $event->values]);
    }

    public function handleFinished(): void
    {
        $this->log('finished', []);
    }

    public function handleStarted(InstallerStarted $event): void
    {
        $this->log('started', ['product' => $event->product]);
    }

    public function handleFailed(InstallerFailed $event): void
    {
        $this->log('failed', ['step' => $event->step, 'error' => $event->exception->getMessage()], 'error');
    }

    public function handleUserCreated(UserCreated $event): void
    {
        // Identifier only — never the password/PII carried on the UserData payload.
        $id = $event->user instanceof Model ? $event->user->getKey() : null;

        $this->log('user.created', ['user' => is_scalar($id) ? $id : null]);
    }

    public function handleUnauthorized(UnauthorizedInstallerAccess $event): void
    {
        $this->log('unauthorized', ['reason' => $event->reason, 'ip' => $event->ip, 'path' => $event->path], 'warning');
    }

    public function subscribe(): array
    {
        return [
            InstallerStarted::class => 'handleStarted',
            StepStarted::class => 'handleStepStarted',
            StepCompleted::class => 'handleStepCompleted',
            StepFailed::class => 'handleStepFailed',
            EnvironmentSaved::class => 'handleEnvironmentSaved',
            UserCreated::class => 'handleUserCreated',
            InstallerFailed::class => 'handleFailed',
            UnauthorizedInstallerAccess::class => 'handleUnauthorized',
            InstallerFinished::class => 'handleFinished',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, array $context, string $level = 'info'): void
    {
        $channel = config('installer.logging.channel');

        Log::channel(is_string($channel) ? $channel : null)->{$level}('installer: ' . $message, $context);
    }
}
