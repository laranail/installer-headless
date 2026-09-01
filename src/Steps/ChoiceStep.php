<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Simtabi\Laranail\Installer\Headless\Events\StepCompleted;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;

/**
 * Generic single-select step (shipped, disabled by default).
 *
 * The no-code way to re-add a Botble-style theme/preset choice — or any
 * branching selection — without writing a class: enable it in config and supply
 * `installer.steps.choice.options` as a `value => label|[...]` map. The chosen
 * value is validated, persisted to {@see InstallationState}, an optional
 * per-option `callback` is invoked (e.g. import a per-theme SQL dump), and a
 * StepCompleted event is fired. Subclass for richer UX.
 */
class ChoiceStep extends AbstractStep
{
    protected string $key = 'choice';

    protected int $defaultPriority = 35;

    public function __construct(private readonly InstallationState $state) {}

    public function run(InstallerContext $context): void
    {
        $options = (array) config('installer.steps.choice.options', []);

        if ($options === []) {
            throw new InstallerException('The choice step is enabled but no `installer.steps.choice.options` are configured.');
        }

        $selected = $context->input('choice');

        if (! is_string($selected) || ! array_key_exists($selected, $options)) {
            throw new InstallerException('A valid option must be selected for the choice step.');
        }

        ($context->state() ?? $this->state)->remember('choice', $selected);
        $context->set('choice', $selected);

        $option = $options[$selected];

        if (is_array($option) && isset($option['callback']) && is_callable($option['callback'])) {
            $option['callback']($selected, $context);
        }

        StepCompleted::dispatch($this->key, ['value' => $selected]);
    }
}
