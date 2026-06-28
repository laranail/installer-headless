<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Enums;

/**
 * Supported database drivers for the environment step, with per-driver
 * connection defaults. Replaces Botble's `DatabaseConnectionsEnum`.
 */
enum DatabaseDriver: string
{
    case Mysql = 'mysql';
    case Mariadb = 'mariadb';
    case Pgsql = 'pgsql';
    case Sqlsrv = 'sqlsrv';
    case Sqlite = 'sqlite';

    public function label(): string
    {
        return match ($this) {
            self::Mysql => 'MySQL',
            self::Mariadb => 'MariaDB',
            self::Pgsql => 'PostgreSQL',
            self::Sqlsrv => 'SQL Server',
            self::Sqlite => 'SQLite',
        };
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::Mysql, self::Mariadb => 3306,
            self::Pgsql => 5432,
            self::Sqlsrv => 1433,
            self::Sqlite => null,
        };
    }

    public function defaultHost(): ?string
    {
        return $this === self::Sqlite ? null : '127.0.0.1';
    }

    public function requiresHost(): bool
    {
        return $this !== self::Sqlite;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
