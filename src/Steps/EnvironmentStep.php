<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Illuminate\Support\Facades\DB;
use Override;
use Simtabi\Laranail\Installer\Headless\Enums\DatabaseDriver;
use Simtabi\Laranail\Installer\Headless\Events\EnvironmentSaved;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Support\DatabaseConnection;
use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\SensitiveFieldDetector;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Collects app + database settings, verifies the database connection, writes the
 * .env (generate from the example, or update an existing file in place), then
 * reconfigures the live connection so the migrate step uses the new credentials.
 */
class EnvironmentStep extends AbstractStep
{
    protected string $key = 'environment';

    protected int $defaultPriority = 30;

    public function __construct(
        private readonly EnvWriter $writer,
        private readonly DatabaseConnection $database,
        private readonly SensitiveFieldDetector $sensitive,
    ) {}

    #[Override]
    protected function stepFields(): array
    {
        $drivers = [];

        foreach (DatabaseDriver::cases() as $driver) {
            $drivers[$driver->value] = $driver->label();
        }

        $notSqlite = ['field' => 'database_driver', 'not' => ['sqlite']];

        // Seed defaults from the existing .env so this step edits in place (not
        // just first-write). Secrets are never pre-filled.
        $env = $this->writer->read((string) (config('installer.env.path') ?: base_path('.env')));

        return [
            new Field('app_name', 'Application name', 'text', $env->get('APP_NAME') ?? config('app.name', 'Laravel'), ['required', 'string', 'max:120']),
            new Field('app_url', 'Application URL', 'text', $env->get('APP_URL') ?? config('app.url', 'http://localhost'), ['required', 'string', 'url']),
            new Field('database_driver', 'Database driver', 'select', $env->get('DB_CONNECTION') ?? 'mysql', ['required', 'string', 'in:' . implode(',', DatabaseDriver::values())], $drivers),
            new Field('database_host', 'Host', 'text', $env->get('DB_HOST') ?? '127.0.0.1', ['required', 'string'], visibleWhen: $notSqlite),
            new Field('database_port', 'Port', 'text', $env->get('DB_PORT') ?? '3306', ['required', 'numeric'], visibleWhen: $notSqlite),
            new Field('database_name', 'Database name', 'text', $env->get('DB_DATABASE') ?? '', ['required', 'string']),
            new Field('database_username', 'Username', 'text', $env->get('DB_USERNAME') ?? '', ['required', 'string'], visibleWhen: $notSqlite),
            new Field('database_password', 'Password', 'password', '', ['nullable', 'string'], sensitive: true, visibleWhen: $notSqlite),
        ];
    }

    public function run(InstallerContext $context): void
    {
        $driver = DatabaseDriver::tryFrom((string) $context->input('database_driver', 'mysql')) ?? DatabaseDriver::Mysql;

        $credentials = $this->credentials($driver, $context);

        if ($driver === DatabaseDriver::Sqlite) {
            $this->ensureSqliteDatabase($credentials['database']);
        }

        $test = $this->database->test($credentials);

        if (! $test['ok']) {
            throw new InstallerException('Database connection failed: ' . $test['message']);
        }

        $values = $this->envValues($driver, $credentials, $context);

        $path = (string) (config('installer.env.path') ?: base_path('.env'));
        $example = (string) (config('installer.env.example') ?: base_path('.env.example'));

        is_file($path)
            ? $this->writer->update($path, $values)
            : $this->writer->generate($example, $path, $values);

        $this->applyRuntimeConnection($driver, $credentials);

        EnvironmentSaved::dispatch($this->sensitive->mask($values));
    }

    /**
     * @return array{driver:string,host?:string,port?:string,database:string,username?:string,password?:string}
     */
    private function credentials(DatabaseDriver $driver, InstallerContext $context): array
    {
        if ($driver === DatabaseDriver::Sqlite) {
            return [
                'driver' => 'sqlite',
                'database' => (string) $context->input('database_name', database_path('database.sqlite')),
            ];
        }

        return [
            'driver' => $driver->value,
            'host' => (string) $context->input('database_host', $driver->defaultHost()),
            'port' => (string) $context->input('database_port', (string) $driver->defaultPort()),
            'database' => (string) $context->input('database_name', ''),
            'username' => (string) $context->input('database_username', ''),
            'password' => (string) $context->input('database_password', ''),
        ];
    }

    /**
     * Create the sqlite database file (and its directory) if it does not exist —
     * Laravel's sqlite driver refuses to connect to a missing file.
     */
    private function ensureSqliteDatabase(string $database): void
    {
        if ($database === ':memory:' || is_file($database)) {
            return;
        }

        $directory = \dirname($database);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @touch($database);
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array<string, string>
     */
    private function envValues(DatabaseDriver $driver, array $credentials, InstallerContext $context): array
    {
        $values = [
            'APP_NAME' => (string) $context->input('app_name', 'Laravel'),
            'APP_URL' => (string) $context->input('app_url', 'http://localhost'),
            'DB_CONNECTION' => $driver->value,
        ];

        if ($driver === DatabaseDriver::Sqlite) {
            $values['DB_DATABASE'] = $credentials['database'];

            return $values;
        }

        return array_merge($values, [
            'DB_HOST' => $credentials['host'] ?? '127.0.0.1',
            'DB_PORT' => $credentials['port'] ?? '',
            'DB_DATABASE' => $credentials['database'],
            'DB_USERNAME' => $credentials['username'] ?? '',
            'DB_PASSWORD' => $credentials['password'] ?? '',
        ]);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function applyRuntimeConnection(DatabaseDriver $driver, array $credentials): void
    {
        $name = $driver->value;

        $config = $driver === DatabaseDriver::Sqlite
            ? ['driver' => 'sqlite', 'database' => $credentials['database'], 'prefix' => '', 'foreign_key_constraints' => true]
            : array_merge($credentials, ['charset' => 'utf8mb4', 'prefix' => '']);

        config([
            'database.default' => $name,
            'database.connections.' . $name => $config,
        ]);

        DB::purge($name);
    }
}
