<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless;

use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Support\ProductPipeline;
use Simtabi\Laranail\Installer\Headless\Support\ProductRegistry;
use Simtabi\Laranail\Installer\Headless\Support\StepFieldHooks;
use Simtabi\Laranail\Installer\Headless\Support\StepPipelines;
use Simtabi\Laranail\Installer\Headless\Users\UserCreationHooks;
use Simtabi\Laranail\Installer\Headless\Users\UserFormHooks;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Fluent, runtime registration DSL — a single entry point a consumer drives from
 * their own service provider's boot() to reshape the installer (steps, products,
 * user-creation hooks, event listeners) without editing package source. Backed by
 * the existing container singletons; it adds no logic of its own.
 *
 * Resolve via the {@see Installer} facade:
 *
 *   Installer::step(new MyStep)
 *       ->before('user', new TermsStep)
 *       ->product('addon', ['type' => 'module'])
 *       ->creating(fn (UserData $d) => null)
 *       ->listen(StepCompleted::class, fn ($e) => ...);
 */
class InstallerManager
{
    use Macroable;

    public function __construct(
        private readonly StepRegistry $steps,
        private readonly ProductRegistry $products,
        private readonly UserCreationHooks $userHooks,
    ) {}

    /** The shared step registry (for advanced use). */
    public function steps(): StepRegistry
    {
        return $this->steps;
    }

    /** The engine, optionally scoped to a product pipeline. */
    public function engine(?string $product = null): InstallerEngine
    {
        $engine = app(InstallerEngine::class);

        return $product !== null && $product !== '' ? $engine->forProduct($product) : $engine;
    }

    // --- Steps ------------------------------------------------------------

    public function step(Step $step): static
    {
        $this->steps->register($step);

        return $this;
    }

    public function before(string $key, Step $step): static
    {
        $this->steps->before($key, $step);

        return $this;
    }

    public function after(string $key, Step $step): static
    {
        $this->steps->after($key, $step);

        return $this;
    }

    public function removeStep(string $key): static
    {
        $this->steps->remove($key);

        return $this;
    }

    /**
     * Add an extra field (or a provider) to any step.
     */
    public function field(string $step, Field|callable $field): static
    {
        app(StepFieldHooks::class)->add($step, $field);

        return $this;
    }

    /**
     * Add a transform stage to a step's input pipeline (runs before validation).
     *
     * @param  class-string|callable  $stage
     */
    public function pipe(string $step, string|callable $stage): static
    {
        app(StepPipelines::class)->pipe($step, $stage);

        return $this;
    }

    // --- Products ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $definition
     */
    public function product(string $slug, array $definition = []): static
    {
        $this->products->register(ProductPipeline::fromConfig($slug, $definition));

        return $this;
    }

    // --- User-creation hooks ---------------------------------------------

    public function preparing(Closure $callback): static
    {
        $this->userHooks->preparing($callback);

        return $this;
    }

    public function creating(Closure $callback): static
    {
        $this->userHooks->creating($callback);

        return $this;
    }

    public function roleAssigning(Closure $callback): static
    {
        $this->userHooks->roleAssigning($callback);

        return $this;
    }

    public function created(Closure $callback): static
    {
        $this->userHooks->created($callback);

        return $this;
    }

    /**
     * Register extra user-form fields for a role (rendered + validated + persisted
     * as attributes). The provider receives the role and context and returns Fields:
     *
     *   Installer::userFields(fn (?string $role, array $ctx) => [
     *       new Field('company', 'Company', 'text', '', ['required', 'string', 'max:120']),
     *   ]);
     *
     * @param  callable(?string, array<string, mixed>): iterable<Field>  $provider
     */
    public function userFields(callable $provider): static
    {
        app(UserFormHooks::class)->fields($provider);

        return $this;
    }

    // --- Events -----------------------------------------------------------

    /**
     * @param  Closure|class-string  $listener
     */
    public function listen(string $event, Closure|string $listener): static
    {
        Event::listen($event, $listener);

        return $this;
    }
}
