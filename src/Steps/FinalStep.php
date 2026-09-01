<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Simtabi\Laranail\Installer\Headless\Events\InstallerFinished;
use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\PostInstallCleanup;
use Throwable;

/**
 * Finalizes installation: runs conservative post-install cleanup, sets the install
 * lock, purges the captured wizard input (no lingering secrets), optionally
 * invalidates a single-use access token, and fires InstallerFinished.
 */
class FinalStep extends AbstractStep
{
    protected string $key = 'final';

    protected int $defaultPriority = 70;

    public function __construct(
        private readonly InstallationState $state,
        private readonly PostInstallCleanup $cleanup,
        private readonly EnvWriter $env,
    ) {}

    public function run(InstallerContext $context): void
    {
        $this->cleanup->handle();

        $state = $context->state() ?? $this->state;
        $state->markInstalled();
        $state->clearInput();

        $this->invalidateSingleUseToken();

        InstallerFinished::dispatch();
    }

    /**
     * Neutralise the access token after a successful install when single-use mode is
     * on (so a leaked token can't be reused). Best-effort: a write failure here must
     * never fail the finished install.
     */
    private function invalidateSingleUseToken(): void
    {
        if (! (bool) config('installer.security.single_use_token', false)) {
            return;
        }

        $hasToken = is_string(config('installer.security.token')) && config('installer.security.token') !== ''
            || is_string(config('installer.security.token_hash')) && config('installer.security.token_hash') !== '';

        if (! $hasToken) {
            return;
        }

        try {
            $path = (string) (config('installer.env.path') ?: base_path('.env'));
            $this->env->update($path, ['INSTALLER_TOKEN' => '', 'INSTALLER_TOKEN_HASH' => '']);
        } catch (Throwable) {
            // Token invalidation is best-effort; never break a completed install.
        }
    }
}
