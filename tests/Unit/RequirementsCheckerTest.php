<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Support\RequirementsChecker;

it('passes for a satisfiable PHP floor and fails for an impossible one', function (): void {
    $checker = new RequirementsChecker;

    expect($checker->checkPhpVersion('8.0.0')['passes'])->toBeTrue()
        ->and($checker->checkPhpVersion('99.0.0')['passes'])->toBeFalse();
});

it('detects loaded and missing extensions', function (): void {
    $result = (new RequirementsChecker)->checkExtensions(['json', 'definitely_not_an_extension']);

    expect($result['json'])->toBeTrue()
        ->and($result['definitely_not_an_extension'])->toBeFalse();
});

it('reports apache modules as null on a non-apache SAPI', function (): void {
    $result = (new RequirementsChecker)->checkApacheModules(['mod_rewrite']);

    expect($result['mod_rewrite'])->toBeNull();
});

it('aggregates a blocking result from required pieces only', function (): void {
    config()->set('installer.requirements', [
        'php' => '8.0.0',
        'extensions' => ['json'],
        'optional' => ['definitely_not_an_extension'],
        'apache' => [],
        'permissions' => [],
    ]);

    $report = (new RequirementsChecker)->all();

    expect($report['passes'])->toBeTrue()
        ->and($report['optional']['definitely_not_an_extension'])->toBeFalse();

    config()->set('installer.requirements.extensions', ['definitely_not_an_extension']);

    expect((new RequirementsChecker)->all()['passes'])->toBeFalse();
});
