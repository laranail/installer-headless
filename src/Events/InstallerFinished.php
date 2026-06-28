<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once installation has completed and the install lock is set.
 */
final class InstallerFinished
{
    use Dispatchable;
}
