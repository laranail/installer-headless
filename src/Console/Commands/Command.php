<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Illuminate\Console\Command as BaseCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base Artisan command for the installer. Enables the `laranail::installer.<command>`
 * naming shape via the shared {@see SupportsNamespacedNames} trait.
 */
abstract class Command extends BaseCommand
{
    use SupportsNamespacedNames;
}
