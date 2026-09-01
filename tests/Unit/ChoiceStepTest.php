<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Installer\Headless\Events\StepCompleted;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Steps\ChoiceStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;

beforeEach(function (): void {
    $this->state = new InstallationState;
    $this->state->clear();
    config()->set('installer.steps.choice.options', ['classic' => 'Classic', 'modern' => 'Modern']);
});

afterEach(fn () => $this->state->clear());

it('stores a valid selection and fires StepCompleted', function (): void {
    Event::fake([StepCompleted::class]);

    $context = InstallerContext::fromInput(['choice' => 'modern']);
    new ChoiceStep($this->state)->run($context);

    expect($this->state->recall('choice'))->toBe('modern')
        ->and($context->get('choice'))->toBe('modern');

    Event::assertDispatched(StepCompleted::class);
});

it('runs the selected option callback', function (): void {
    $called = null;
    config()->set('installer.steps.choice.options', [
        'classic' => ['label' => 'Classic', 'callback' => function (string $value) use (&$called): void {
            $called = $value;
        }],
    ]);

    new ChoiceStep($this->state)->run(InstallerContext::fromInput(['choice' => 'classic']));

    expect($called)->toBe('classic');
});

it('rejects an invalid selection', function (): void {
    expect(fn () => new ChoiceStep($this->state)->run(InstallerContext::fromInput(['choice' => 'nope'])))
        ->toThrow(InstallerException::class);
});

it('fails when enabled with no options configured', function (): void {
    config()->set('installer.steps.choice.options', []);

    expect(fn () => new ChoiceStep($this->state)->run(InstallerContext::fromInput(['choice' => 'x'])))
        ->toThrow(InstallerException::class);
});
