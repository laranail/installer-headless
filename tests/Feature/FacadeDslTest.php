<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\InstallerManager;
use Simtabi\Laranail\Installer\Headless\Steps\AbstractStep;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Steps\WelcomeStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;

class DslFakeStep extends AbstractStep
{
    protected string $key = 'dsl-fake';

    protected int $defaultPriority = 5;

    public function run(InstallerContext $context): void {}
}

it('registers a step via the facade DSL and returns the manager for chaining', function (): void {
    $result = Installer::step(new DslFakeStep);

    expect($result)->toBeInstanceOf(InstallerManager::class)
        ->and(app(StepRegistry::class)->has('dsl-fake'))->toBeTrue();
});

it('registers a product pipeline via the facade DSL', function (): void {
    Installer::product('addon', ['steps' => ['requirements', 'final']]);

    $keys = array_map(static fn (Step $s): string => $s->key(), Installer::engine('addon')->orderedSteps());

    expect($keys)->toBe(['requirements', 'final']);
});

it('allows subclassing + replacing a step now that classes are not final', function (): void {
    Installer::step(new class(app(InstallationState::class)) extends WelcomeStep
    {
        public function label(): string
        {
            return 'Decorated welcome';
        }
    });

    expect(app(StepRegistry::class)->get('welcome'))->toBeInstanceOf(WelcomeStep::class)
        ->and(app(StepRegistry::class)->get('welcome')->label())->toBe('Decorated welcome');
});

it('supports macros on the engine and the registry', function (): void {
    InstallerEngine::macro('activeStepCount', fn (): int => count($this->orderedSteps()));
    StepRegistry::macro('allKeys', fn (): array => array_map(static fn (Step $s): string => $s->key(), $this->all()));

    expect(app(InstallerEngine::class)->activeStepCount())->toBeGreaterThan(0)
        ->and(app(StepRegistry::class)->allKeys())->toContain('user');
});
