<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Wizard\Field;

it('is always visible without a condition', function (): void {
    expect(new Field('x', 'X')->isVisible([]))->toBeTrue();
});

it('honors in / not / equals visibility conditions', function (): void {
    $in = new Field('x', 'X', visibleWhen: ['field' => 'driver', 'in' => ['mysql', 'pgsql']]);
    $not = new Field('x', 'X', visibleWhen: ['field' => 'driver', 'not' => ['sqlite']]);
    $eq = new Field('x', 'X', visibleWhen: ['field' => 'mode', 'equals' => 'advanced']);

    expect($in->isVisible(['driver' => 'mysql']))->toBeTrue()
        ->and($in->isVisible(['driver' => 'sqlite']))->toBeFalse()
        ->and($not->isVisible(['driver' => 'sqlite']))->toBeFalse()
        ->and($not->isVisible(['driver' => 'mysql']))->toBeTrue()
        ->and($eq->isVisible(['mode' => 'advanced']))->toBeTrue()
        ->and($eq->isVisible(['mode' => 'basic']))->toBeFalse();
});
