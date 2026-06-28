<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Enums\DatabaseDriver;

it('exposes its string values', function (): void {
    expect(DatabaseDriver::values())->toContain('mysql', 'pgsql', 'sqlite');
});

it('provides per-driver connection defaults', function (): void {
    expect(DatabaseDriver::Mysql->defaultPort())->toBe(3306)
        ->and(DatabaseDriver::Pgsql->defaultPort())->toBe(5432)
        ->and(DatabaseDriver::Sqlite->defaultPort())->toBeNull()
        ->and(DatabaseDriver::Mysql->requiresHost())->toBeTrue()
        ->and(DatabaseDriver::Sqlite->requiresHost())->toBeFalse()
        ->and(DatabaseDriver::Pgsql->label())->toBe('PostgreSQL');
});
