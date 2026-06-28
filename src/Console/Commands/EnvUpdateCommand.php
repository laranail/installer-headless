<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;

/**
 * Updates a single key in the .env file (format-preserving, atomic), via the
 * core EnvWriter — useful in CI/deploy scripts.
 */
final class EnvUpdateCommand extends Command
{
    protected $signature = 'laranail::installer.env {key : The .env key} {value : The new value}';

    protected $description = 'Set a key in the .env file (preserves comments, atomic).';

    public function handle(EnvWriter $writer): int
    {
        $path = (string) (config('installer.env.path') ?: base_path('.env'));
        $key = (string) $this->argument('key');

        $writer->update($path, [$key => (string) $this->argument('value')]);

        $this->info("Updated {$key} in {$path}.");

        return self::SUCCESS;
    }
}
