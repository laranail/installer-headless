<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless;

use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Events\StepCompleted;
use Simtabi\Laranail\Installer\Headless\Events\StepFailed;
use Simtabi\Laranail\Installer\Headless\Events\StepStarted;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\ProductPipeline;
use Simtabi\Laranail\Installer\Headless\Support\ProductRegistry;
use Simtabi\Laranail\Installer\Headless\Support\StepPipelines;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;
use Simtabi\Laranail\Installer\Headless\Wizard\WizardValidator;
use Throwable;

/**
 * The single wizard engine that drives the install pipeline. The web layer and the
 * CLI both go through this — it owns ordering, navigation, ordering guards,
 * validation (the one validation path), per-step input persistence/resume,
 * per-step events and the install lock. Front ends only collect input and call in.
 */
class InstallerEngine
{
    use Macroable;

    public function __construct(
        private readonly StepRegistry $registry,
        private readonly InstallationState $state,
        private readonly WizardValidator $validator,
        private readonly ?ProductPipeline $pipeline = null,
    ) {}

    public function steps(): StepRegistry
    {
        return $this->registry;
    }

    /**
     * The steps this engine will actually run, in order — the resolved active
     * pipeline (product-scoped when applicable). For the CLI dashboard and any
     * consumer that needs to iterate the pipeline.
     *
     * @return list<Step>
     */
    public function orderedSteps(): array
    {
        return $this->activeSteps();
    }

    /**
     * An engine scoped to a product: its install lifecycle, progress and resume
     * state are isolated (per-product markers) AND it runs the product's own
     * pipeline — the steps/order/priorities declared for that product (via
     * `installer.products.<slug>` or a runtime {@see ProductRegistry} registration).
     * An unknown product, or one with no `steps`, runs the full default pipeline, so
     * single-product is just the degenerate case of this one engine.
     */
    public function forProduct(?string $product): self
    {
        $pipeline = app(ProductRegistry::class)->get($product);

        $registry = $pipeline !== null && $pipeline->steps !== []
            ? $this->registry->select($pipeline->steps, $pipeline->priorities)
            : $this->registry;

        // Only scope the state when there's an actual product; for the default case
        // reuse the same state instance so its cache stays consistent with callers.
        $state = $product !== null && $product !== ''
            ? $this->state->forProduct($product)
            : $this->state;

        return new self($registry, $state, $this->validator, $pipeline);
    }

    /**
     * The steps active for this engine: for a product-scoped pipeline the selected
     * set IS the enabled set (so a product may include a globally-disabled step like
     * `license`); for the default pipeline it's the config-driven enabled set.
     *
     * @return list<Step>
     */
    private function activeSteps(): array
    {
        return $this->pipeline instanceof ProductPipeline && $this->pipeline->steps !== []
            ? $this->registry->all()
            : $this->registry->enabled();
    }

    // --- Navigation -------------------------------------------------------

    /** The first active step not yet completed (or the last active step if all done). */
    public function current(): ?Step
    {
        $active = $this->activeSteps();

        foreach ($active as $step) {
            if (! $this->state->isStepComplete($step->key())) {
                return $step;
            }
        }

        return $active === [] ? null : $active[count($active) - 1];
    }

    public function next(string $key): ?Step
    {
        $active = $this->activeSteps();

        foreach ($active as $i => $step) {
            if ($step->key() === $key) {
                return $active[$i + 1] ?? null;
            }
        }

        return null;
    }

    public function previous(string $key): ?Step
    {
        $active = $this->activeSteps();

        foreach ($active as $i => $step) {
            if ($step->key() === $key) {
                return $active[$i - 1] ?? null;
            }
        }

        return null;
    }

    /**
     * Ordering guard: a step may be accessed only up to the current (first
     * incomplete) step — completed steps and the current one are allowed; later
     * steps are blocked until earlier ones complete.
     */
    public function canAccess(string $key): bool
    {
        $keys = array_map(static fn (Step $s): string => $s->key(), $this->activeSteps());
        $target = array_search($key, $keys, true);

        if ($target === false) {
            return false;
        }

        $firstIncomplete = count($keys);

        foreach ($keys as $i => $candidate) {
            if (! $this->state->isStepComplete($candidate)) {
                $firstIncomplete = $i;
                break;
            }
        }

        return $target <= $firstIncomplete;
    }

    /**
     * @return array{total:int, completed:int, current:?string}
     */
    public function progress(): array
    {
        $active = $this->activeSteps();
        $completed = array_filter($active, fn (Step $s): bool => $this->state->isStepComplete($s->key()));

        return [
            'total' => count($active),
            'completed' => count($completed),
            'current' => $this->current()?->key(),
        ];
    }

    // --- Fields / rules (single source for both front ends) ---------------

    /**
     * @return list<Field>
     */
    public function fields(string $key): array
    {
        return $this->step($key)->fields();
    }

    /**
     * Current values for a step's fields: persisted input re-hydrated over defaults.
     *
     * @return array<string, mixed>
     */
    public function values(string $key): array
    {
        $step = $this->step($key);
        $defaults = method_exists($step, 'defaults') ? $step->defaults() : [];

        return array_merge($defaults, $this->state->recallInput($key));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function rules(string $key, array $input = []): array
    {
        return $this->step($key)->rules($input);
    }

    // --- Execution --------------------------------------------------------

    /**
     * Validate + run a single step from collected input, returning the next step key.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException|InstallerException
     */
    public function submit(string $key, array $input): ?string
    {
        $this->runStep($key, InstallerContext::fromInput($input));

        return $this->next($key)?->key();
    }

    /**
     * Run a single step by key: guard → validate → persist input → execute →
     * mark complete, emitting lifecycle events.
     *
     * @throws ValidationException|InstallerException
     */
    public function runStep(string $key, InstallerContext $context, bool $validate = true): void
    {
        $this->withConfigOverlay(function () use ($key, $context, $validate): void {
            $step = $this->step($key);

            $this->ensureInstalling();
            $this->stampContext($context);

            if (! $this->canAccess($key)) {
                throw new InstallerException("Installer step [{$key}] cannot run yet — complete the earlier steps first.");
            }

            $this->process($step, $context, $validate);
        });
    }

    /**
     * Run the full pipeline (enabled steps, in order). Completed steps are
     * skipped when $resume is true, making re-runs safe and resumable.
     *
     * @throws ValidationException|InstallerException
     */
    public function run(InstallerContext $context, bool $resume = true): void
    {
        $this->withConfigOverlay(function () use ($context, $resume): void {
            $this->ensureInstalling();
            $this->stampContext($context);

            foreach ($this->activeSteps() as $step) {
                if ($resume && $this->state->isStepComplete($step->key())) {
                    continue;
                }

                $this->process($step, $context, true);
            }
        });
    }

    private function process(Step $step, InstallerContext $context, bool $validate): void
    {
        // Per-step transform stages run before validation (normalise/enrich/veto).
        $context->replaceInput(app(StepPipelines::class)->process($step->key(), $context->allInput()));

        if ($validate) {
            // Single validation path; ValidationException propagates to the caller
            // so front ends can surface per-field errors.
            $this->validator->validate($step, $context->allInput());
        }

        $this->state->rememberInput($step->key(), $context->allInput());

        $this->execute($step, $context);
    }

    private function step(string $key): Step
    {
        return $this->registry->get($key) ?? throw new InstallerException("Unknown installer step [{$key}].");
    }

    /**
     * Make the engine's (product-scoped) state and pipeline visible to steps via the
     * context, so steps write to the correct per-product markers/state instead of an
     * injected singleton.
     */
    private function stampContext(InstallerContext $context): void
    {
        $context->setState($this->state);

        if ($this->pipeline instanceof ProductPipeline) {
            $context->setProduct($this->pipeline);
        }
    }

    /**
     * Run $fn with the product's config merged into global installer.* config (opt-in
     * via the pipeline's configOverlay), restoring the originals afterwards so nothing
     * leaks across products (e.g. between --all-products iterations).
     *
     * @param  callable(): void  $fn
     */
    private function withConfigOverlay(callable $fn): void
    {
        if (! $this->pipeline instanceof ProductPipeline || ! $this->pipeline->configOverlay || $this->pipeline->config === []) {
            $fn();

            return;
        }

        // Snapshot the whole installer.* subtree so the restore is exact — any keys
        // the overlay adds are removed again, not left behind as nulls.
        $snapshot = config('installer');

        foreach ($this->pipeline->config as $key => $value) {
            config(["installer.{$key}" => $value]);
        }

        try {
            $fn();
        } finally {
            config(['installer' => $snapshot]);
        }
    }

    private function ensureInstalling(): void
    {
        if (! $this->state->isInstalling() && ! $this->state->hasInstalledMarker()) {
            $this->state->markInstalling();
        }
    }

    private function execute(Step $step, InstallerContext $context): void
    {
        StepStarted::dispatch($step->key());

        try {
            $step->run($context);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            StepFailed::dispatch($step->key(), $exception);

            throw $exception instanceof InstallerException
                ? $exception
                : new InstallerException("Step [{$step->key()}] failed: {$exception->getMessage()}", previous: $exception);
        }

        $this->state->markStepComplete($step->key());

        StepCompleted::dispatch($step->key());
    }
}
