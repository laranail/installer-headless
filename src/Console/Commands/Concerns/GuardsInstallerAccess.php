<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands\Concerns;

use Simtabi\Laranail\Installer\Headless\Security\InstallerAccessPolicy;

/**
 * Token gate for state-changing CLI commands. When an installer token is configured
 * (and the layer isn't locally bypassed), a valid `--token` is required; the
 * availability window is enforced too when window_applies_to_cli. Expiring signed
 * links are HTTP-only, so the CLI authenticates with the token.
 *
 * Used only by commands that declare a `--token=` option.
 */
trait GuardsInstallerAccess
{
    protected function guardAccess(): bool
    {
        $policy = app(InstallerAccessPolicy::class);

        if ($policy->windowConfigured()
            && (bool) config('installer.security.window_applies_to_cli', false)
            && ! $policy->withinWindow()) {
            $this->error('The installer is outside its allowed availability window.');

            return false;
        }

        if ($policy->unrestricted() || ! $policy->tokenConfigured()) {
            return true;
        }

        $token = $this->option('token');

        if (is_string($token) && $policy->tokenValid($token)) {
            return true;
        }

        $this->error('A valid --token is required (an installer access token is configured).');

        return false;
    }
}
