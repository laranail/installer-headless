<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Simtabi\Laranail\Installer\Headless\Support\InstallationState;

/**
 * Reports installation status (markers, DB readiness, app key, completed steps).
 */
final class StatusCommand extends Command
{
    protected $signature = 'laranail::installer.status';

    protected $description = 'Show the current installation status.';

    public function handle(InstallationState $state): int
    {
        $rows = [];

        foreach ($state->status() as $key => $value) {
            $rows[] = [$key, $value ? 'yes' : 'no'];
        }

        $this->table(['Check', 'Value'], $rows);

        $completed = $state->completedSteps();
        $this->line('Completed steps: ' . ($completed === [] ? '(none)' : implode(', ', $completed)));

        return self::SUCCESS;
    }
}
