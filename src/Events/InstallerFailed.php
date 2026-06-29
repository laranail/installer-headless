<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * Fired once when a step throws and halts the install — the top-level "install
 * failed" signal, alongside the per-step {@see StepFailed}. Consumers can listen
 * to this single event instead of inferring failure from each step.
 */
final readonly class InstallerFailed
{
    use Dispatchable;

    public function __construct(public ?string $step, public Throwable $exception) {}
}
