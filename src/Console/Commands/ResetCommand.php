<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Illuminate\Support\Facades\App;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;

/**
 * Clears installer state (markers + step progress) so the wizard can run again.
 * Refuses to run in production unless --force is given.
 */
final class ResetCommand extends Command
{
    protected $signature = 'laranail::installer.reset {--force : Allow running in production}';

    protected $description = 'Reset installer state (dev/local).';

    public function handle(InstallationState $state): int
    {
        if (App::environment('production') && ! $this->option('force')) {
            $this->error('Refusing to reset installer state in production. Use --force to override.');

            return self::FAILURE;
        }

        $state->clear();
        $this->info('Installer state cleared.');

        return self::SUCCESS;
    }
}
