<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an installer step finishes successfully.
 */
final readonly class StepCompleted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context  non-sensitive result data
     */
    public function __construct(public string $step, public array $context = []) {}
}
