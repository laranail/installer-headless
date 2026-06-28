<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\InstallerManager;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * @method static StepRegistry steps()
 * @method static InstallerEngine engine(?string $product = null)
 * @method static InstallerManager step(Step $step)
 * @method static InstallerManager before(string $key, Step $step)
 * @method static InstallerManager after(string $key, Step $step)
 * @method static InstallerManager removeStep(string $key)
 * @method static InstallerManager field(string $step, Field|callable $field)
 * @method static InstallerManager pipe(string $step, string|callable $stage)
 * @method static InstallerManager product(string $slug, array $definition = [])
 * @method static InstallerManager preparing(Closure $callback)
 * @method static InstallerManager creating(Closure $callback)
 * @method static InstallerManager roleAssigning(Closure $callback)
 * @method static InstallerManager created(Closure $callback)
 * @method static InstallerManager listen(string $event, Closure|string $listener)
 *
 * @see InstallerManager
 */
final class Installer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InstallerManager::class;
    }
}
