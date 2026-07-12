<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Support\Facades\Artisan;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Throwable;

/**
 * Runs database migrations (and an optional seeder) for the installer. Wraps
 * Artisan so failures surface as typed exceptions rather than silent exit codes.
 */
final class MigrationRunner
{
    /**
     * @param  string|null  $seeder  optional seeder class name; null skips seeding
     */
    public function run(?string $seeder = null): void
    {
        $this->call('migrate', ['--force' => true]);

        if ($seeder !== null && $seeder !== '') {
            $this->call('db:seed', ['--force' => true, '--class' => $seeder]);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function call(string $command, array $parameters): void
    {
        try {
            $exitCode = Artisan::call($command, $parameters);
        } catch (Throwable $exception) {
            throw new InstallerException("Failed running `{$command}`: {$exception->getMessage()}", $exception->getCode(), previous: $exception);
        }

        if ($exitCode !== 0) {
            throw new InstallerException("Command `{$command}` exited with code {$exitCode}. " . trim(Artisan::output()));
        }
    }
}
