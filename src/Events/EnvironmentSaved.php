<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the .env file is written/updated during installation.
 */
final readonly class EnvironmentSaved
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $values  the keys written (secrets already masked by the caller)
     */
    public function __construct(public array $values = []) {}
}
