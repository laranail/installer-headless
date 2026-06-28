<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Support\DatabaseConnection;

it('reports the default test connection as live', function (): void {
    expect((new DatabaseConnection)->connected())->toBeTrue();
});

it('succeeds testing an in-memory sqlite connection', function (): void {
    $result = (new DatabaseConnection)->test([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    expect($result['ok'])->toBeTrue();
});

it('returns false for a missing table', function (): void {
    expect((new DatabaseConnection)->hasTable('a_table_that_does_not_exist'))->toBeFalse();
});
