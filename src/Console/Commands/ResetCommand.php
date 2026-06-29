<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Illuminate\Support\Facades\App;
use Simtabi\Laranail\Installer\Headless\Console\Commands\Concerns\GuardsInstallerAccess;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;

/**
 * Clears installer state (markers + step progress) so the wizard can run again.
 * Refuses to run in production unless --force is given.
 */
final class ResetCommand extends Command
{
    use GuardsInstallerAccess;

    protected $signature = 'laranail::installer.reset {--token= : Installer access token (required when one is configured)} {--force : Allow running in production}';

    protected $description = 'Reset installer state (dev/local).';

    public function handle(InstallationState $state): int
    {
        if (! $this->guardAccess()) {
            return self::FAILURE;
        }

        if (App::environment('production') && ! $this->option('force')) {
            $this->error('Refusing to reset installer state in production. Use --force to override.');

            return self::FAILURE;
        }

        $state->clear();
        $this->info('Installer state cleared.');

        return self::SUCCESS;
    }
}
