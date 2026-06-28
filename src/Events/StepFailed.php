<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * Fired when an installer step throws.
 */
final readonly class StepFailed
{
    use Dispatchable;

    public function __construct(public string $step, public Throwable $exception) {}
}
