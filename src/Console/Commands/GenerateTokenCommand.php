<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;

/**
 * Generates a secure installer gate token (and its hash). With --write it stores
 * INSTALLER_TOKEN (or INSTALLER_TOKEN_HASH with --hash) into the app's .env via the
 * atomic EnvWriter — usable on hosts without shell access to set env vars manually.
 */
final class GenerateTokenCommand extends Command
{
    protected $signature = 'laranail::installer.token {--write : Write the token to the app .env} {--hash : Store the hashed token (INSTALLER_TOKEN_HASH) instead of the raw value}';

    protected $description = 'Generate a secure installer access token (optionally writing it to .env).';

    public function handle(EnvWriter $env): int
    {
        $token = Str::random(64);
        $hash = Hash::make($token);

        $this->line('Installer token: <info>' . $token . '</info>');
        $this->line('Token hash:      ' . $hash);

        if (! $this->option('write') && ! $this->option('hash')) {
            $this->newLine();
            $this->comment('Add INSTALLER_TOKEN to your .env, or re-run with --write (or --hash) to store it.');

            return self::SUCCESS;
        }

        $path = config('installer.env.path') ?: base_path('.env');
        $useHash = (bool) $this->option('hash');
        $key = $useHash ? 'INSTALLER_TOKEN_HASH' : 'INSTALLER_TOKEN';

        $env->update((string) $path, [$key => $useHash ? $hash : $token]);

        $this->info("Wrote {$key} to {$path}");

        return self::SUCCESS;
    }
}
