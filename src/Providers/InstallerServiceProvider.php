<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Providers;

use Composer\InstalledVersions;
use Illuminate\Contracts\Events\Dispatcher;
use Override;
use Simtabi\Laranail\Installer\Headless\Console\Commands\EnvUpdateCommand;
use Simtabi\Laranail\Installer\Headless\Console\Commands\GenerateTokenCommand;
use Simtabi\Laranail\Installer\Headless\Console\Commands\InstallCommand;
use Simtabi\Laranail\Installer\Headless\Console\Commands\ResetCommand;
use Simtabi\Laranail\Installer\Headless\Console\Commands\StatusCommand;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Doctor\Checks;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\InstallerManager;
use Simtabi\Laranail\Installer\Headless\Security\InstallerAccessPolicy;
use Simtabi\Laranail\Installer\Headless\Steps\ChoiceStep;
use Simtabi\Laranail\Installer\Headless\Steps\CreateUserStep;
use Simtabi\Laranail\Installer\Headless\Steps\EnvironmentStep;
use Simtabi\Laranail\Installer\Headless\Steps\FinalStep;
use Simtabi\Laranail\Installer\Headless\Steps\ImportDatabaseStep;
use Simtabi\Laranail\Installer\Headless\Steps\ImportUsersStep;
use Simtabi\Laranail\Installer\Headless\Steps\LicenseStep;
use Simtabi\Laranail\Installer\Headless\Steps\MigrateStep;
use Simtabi\Laranail\Installer\Headless\Steps\RequirementsStep;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Steps\WelcomeStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerEventLogger;
use Simtabi\Laranail\Installer\Headless\Support\InstallerNotifier;
use Simtabi\Laranail\Installer\Headless\Support\ProductRegistry;
use Simtabi\Laranail\Installer\Headless\Support\StepFieldHooks;
use Simtabi\Laranail\Installer\Headless\Support\StepPipelines;
use Simtabi\Laranail\Installer\Headless\Users\RoleManager;
use Simtabi\Laranail\Installer\Headless\Users\UserCreationHooks;
use Simtabi\Laranail\Installer\Headless\Users\UserFormHooks;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;

/**
 * Service provider for the headless installer engine.
 *
 * Registers the flat `installer.*` config, translations and the engine's
 * container bindings (support services, the default step pipeline and the
 * {@see InstallerEngine}). It holds NO web logic — the web layer ships
 * separately and drives the engine through its public API.
 */
final class InstallerServiceProvider extends PackageServiceProvider
{
    /** @var list<class-string<Step>> */
    private const array DEFAULT_STEPS = [
        WelcomeStep::class,
        RequirementsStep::class,
        EnvironmentStep::class,
        ChoiceStep::class,
        ImportDatabaseStep::class,
        MigrateStep::class,
        CreateUserStep::class,
        ImportUsersStep::class,
        LicenseStep::class,
        FinalStep::class,
    ];

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/installer-headless')
            ->hasConfigFile('installer')
            ->withoutConfigNamespacing()
            ->hasTranslations('installer')
            ->hasCommands(
                InstallCommand::class,
                StatusCommand::class,
                ResetCommand::class,
                EnvUpdateCommand::class,
                GenerateTokenCommand::class,
            )
            ->hasAboutSection(
                AboutSectionDefinition::make('Installer')
                    ->field('Version', fn (): string => (string) InstalledVersions::getPrettyVersion('laranail/installer-headless'))
                    ->field('Enabled', fn (): string => config('installer.enabled') ? 'yes' : 'no')
            )
            ->hasDoctorChecks(Checks::all());
    }

    #[Override]
    public function packageBooted(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->subscribe(InstallerEventLogger::class);
        $dispatcher->subscribe(InstallerNotifier::class);
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(InstallationState::class);
        $this->app->singleton(InstallerAccessPolicy::class);
        $this->app->singleton(UserCreationHooks::class);
        $this->app->singleton(UserFormHooks::class);
        $this->app->singleton(RoleManager::class);
        $this->app->singleton(ProductRegistry::class);
        $this->app->singleton(StepFieldHooks::class);
        $this->app->singleton(StepPipelines::class);

        $this->app->singleton(StepRegistry::class, function ($app): StepRegistry {
            $registry = new StepRegistry;

            foreach (self::DEFAULT_STEPS as $step) {
                $registry->register($app->make($step));
            }

            return $registry;
        });

        $this->app->singleton(InstallerEngine::class);
        $this->app->singleton(InstallerManager::class);
    }
}
