<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Simtabi\Laranail\Installer\Headless\Console\Commands\Concerns\GuardsInstallerAccess;
use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;

/**
 * Updates a single key in the .env file (format-preserving, atomic), via the
 * core EnvWriter — useful in CI/deploy scripts.
 */
final class EnvUpdateCommand extends Command
{
    use GuardsInstallerAccess;

    protected $signature = 'laranail::installer.env {key : The .env key} {value : The new value} {--token= : Installer access token (required when one is configured)}';

    protected $description = 'Set a key in the .env file (preserves comments, atomic).';

    public function handle(EnvWriter $writer): int
    {
        if (! $this->guardAccess()) {
            return self::FAILURE;
        }

        $path = (string) (config('installer.env.path') ?: base_path('.env'));

        /** @var string $key */
        $key = $this->argument('key');
        /** @var string $value */
        $value = $this->argument('value');

        $writer->update($path, [$key => $value]);

        $this->info("Updated {$key} in {$path}.");

        return self::SUCCESS;
    }
}
