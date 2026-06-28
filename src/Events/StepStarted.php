<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an installer step begins executing.
 */
final readonly class StepStarted
{
    use Dispatchable;

    public function __construct(public string $step) {}
}
