<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Steps\ImportDatabaseStep;
use Simtabi\Laranail\Installer\Headless\Steps\ImportUsersStep;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\User;

function importUsersTable(): void
{
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->timestamps();
    });

    config()->set('installer.user.model', User::class);
    config()->set('installer.user.role_driver', 'eloquent');
}

afterEach(function (): void {
    Schema::dropIfExists('users');
    Schema::dropIfExists('widgets');
});

it('registers both import steps disabled (config-toggleable)', function (): void {
    $registry = app(StepRegistry::class);

    expect($registry->has('import-users'))->toBeTrue()
        ->and($registry->has('import-database'))->toBeTrue()
        ->and($registry->get('import-users')->isEnabled())->toBeFalse()
        ->and($registry->get('import-database')->isEnabled())->toBeFalse();
});

it('imports users from a CSV file', function (): void {
    importUsersTable();
    $csv = sys_get_temp_dir() . '/users-' . uniqid() . '.csv';
    file_put_contents($csv, "name,email,password,role\nAda,ada@x.test,secret-pass,admin\nBo,bo@x.test,secret-pass,member\n");
    config()->set('installer.users.import.path', $csv);

    (new ImportUsersStep)->run(InstallerContext::fromInput([]));

    expect(User::query()->count())->toBe(2)
        ->and(User::query()->where('email', 'ada@x.test')->value('role'))->toBe('admin');

    @unlink($csv);
});

it('imports users from an inline rows array', function (): void {
    importUsersTable();
    config()->set('installer.users.import.rows', [
        ['name' => 'Cy', 'email' => 'cy@x.test', 'password' => 'secret-pass', 'role' => 'member'],
    ]);

    (new ImportUsersStep)->run(InstallerContext::fromInput([]));

    expect(User::query()->where('email', 'cy@x.test')->exists())->toBeTrue();
});

it('imports a SQL dump via database-tools', function (): void {
    $sql = sys_get_temp_dir() . '/dump-' . uniqid() . '.sql';
    file_put_contents($sql, "CREATE TABLE widgets (id integer primary key autoincrement, name varchar(50));\nINSERT INTO widgets (name) VALUES ('gear');\n");
    config()->set('installer.database.import.path', $sql);

    (new ImportDatabaseStep)->run(InstallerContext::fromInput([]));

    expect(DB::table('widgets')->count())->toBe(1);

    @unlink($sql);
});
