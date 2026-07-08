<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Override;
use Simtabi\Laranail\DbTools\Backup\SqlFileRestorer;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Imports a SQL dump via `laranail/db-tools` (`SqlFileRestorer`).
 *
 * Off by default; enable via `installer.steps.import-database.enabled` or
 * `Installer::step(new ImportDatabaseStep)`. Source: the `path` field / config
 * `installer.database.import.path`, on `installer.database.import.connection`.
 * `db-tools` is an optional (`suggest`) dependency — resolved lazily so the
 * step never breaks boot when it's absent.
 */
class ImportDatabaseStep extends AbstractStep
{
    protected string $key = 'import-database';

    protected int $defaultPriority = 45;

    #[Override]
    protected function stepFields(): array
    {
        return [
            new Field('path', 'SQL dump path', 'text', (string) config('installer.database.import.path', ''), ['nullable', 'string']),
        ];
    }

    public function run(InstallerContext $context): void
    {
        $this->raiseTimeLimit();

        $path = (string) ($context->input('path') ?? config('installer.database.import.path', ''));

        if ($path === '') {
            return;
        }

        if (! class_exists(SqlFileRestorer::class)) {
            throw new InstallerException('Database import requires laranail/db-tools — install it with `composer require laranail/db-tools`.');
        }

        $connection = config('installer.database.import.connection');

        app(SqlFileRestorer::class)->restore($path, is_string($connection) ? $connection : null);
    }
}
