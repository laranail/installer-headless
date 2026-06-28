<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Illuminate\Support\Facades\App;
use Override;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Records the chosen UI locale (from the configured `installer.locales`) and
 * applies it for the remainder of the run.
 */
class WelcomeStep extends AbstractStep
{
    protected string $key = 'welcome';

    protected int $defaultPriority = 10;

    public function __construct(private readonly InstallationState $state) {}

    #[Override]
    protected function stepFields(): array
    {
        /** @var array<string, string> $locales */
        $locales = (array) config('installer.locales', ['en' => 'English']);

        return [
            new Field(
                name: 'locale',
                label: 'Language',
                type: 'select',
                default: (string) array_key_first($locales),
                rules: ['required', 'string', 'in:' . implode(',', array_keys($locales))],
                options: $locales,
            ),
        ];
    }

    public function run(InstallerContext $context): void
    {
        $locales = (array) config('installer.locales', ['en' => 'English']);
        $locale = (string) $context->input('locale', App::getLocale());

        if (! array_key_exists($locale, $locales)) {
            $locale = (string) array_key_first($locales);
        }

        App::setLocale($locale);
        ($context->state() ?? $this->state)->remember('locale', $locale);
        $context->set('locale', $locale);
    }
}
