<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Thin helper around database connectivity used by the installer guards and the
 * environment step. Connection failures never throw out of the test methods —
 * they return a structured result the UI/CLI can surface.
 */
final class DatabaseConnection
{
    private const string TEST_CONNECTION = 'installer_connection_test';

    /**
     * Whether the given (or default) connection can open a live PDO handle.
     */
    public function connected(?string $name = null): bool
    {
        try {
            DB::connection($name)->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function hasTable(string $table, ?string $name = null): bool
    {
        try {
            return Schema::connection($name)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Attempt a connection with ad-hoc credentials (before .env is written).
     *
     * @param  array{driver?:string,host?:string,port?:int|string,database?:string,username?:string,password?:string}  $credentials
     * @return array{ok:bool, message:string}
     */
    public function test(array $credentials): array
    {
        $driver = $credentials['driver'] ?? 'mysql';

        $config = $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'database' => $credentials['database'] ?? ':memory:', 'prefix' => '']
            : [
                'driver' => $driver,
                'host' => $credentials['host'] ?? '127.0.0.1',
                'port' => (string) ($credentials['port'] ?? ''),
                'database' => $credentials['database'] ?? '',
                'username' => $credentials['username'] ?? '',
                'password' => $credentials['password'] ?? '',
                'charset' => 'utf8mb4',
                'prefix' => '',
            ];

        config()->set('database.connections.' . self::TEST_CONNECTION, $config);

        try {
            DB::purge(self::TEST_CONNECTION);
            DB::connection(self::TEST_CONNECTION)->getPdo();

            return ['ok' => true, 'message' => 'Connection successful.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } finally {
            DB::purge(self::TEST_CONNECTION);
            config()->offsetUnset('database.connections.' . self::TEST_CONNECTION);
        }
    }
}
