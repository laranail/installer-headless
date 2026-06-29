<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Support\RequirementsChecker;

it('treats a not-yet-created path as writable when its parent directory is', function (): void {
    $missing = '.env-missing-' . uniqid();
    config()->set('installer.requirements.permissions', [$missing]);

    $report = (new RequirementsChecker)->checkPermissions([$missing]);

    // base_path() is writable in the test app, and the file doesn't exist, so the
    // parent-directory check applies (the fresh-install .env false-negative is fixed).
    expect($report[$missing])->toBe(is_writable(base_path()));
});

it('includes non-blocking warnings in the report without affecting passes', function (): void {
    config()->set('installer.requirements.php', '8.0');
    config()->set('installer.requirements.extensions', []);
    config()->set('installer.requirements.permissions', []);
    config()->set('session.driver', 'database');

    $report = (new RequirementsChecker)->all();

    expect($report)->toHaveKey('warnings')
        ->and($report['warnings'])->toHaveKeys([
            'disable_functions', 'max_execution_time', 'memory_limit', 'session_driver', 'cache_store',
        ])
        // A db-backed session driver is flagged (ok=false) but never blocks.
        ->and($report['warnings']['session_driver']['ok'])->toBeFalse()
        ->and($report['passes'])->toBeTrue();
});
