<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;

/**
 * Ordered, mutable collection of install steps.
 *
 * Steps are keyed by {@see Step::key()} and ordered by {@see Step::priority()}.
 * Consumers register/insert/reorder/replace/remove steps at runtime (typically
 * from their own service provider's boot) without editing the package. Enabled
 * state is driven by each step (config-backed). Registering a key that already
 * exists replaces it.
 */
class StepRegistry
{
    use Macroable;

    /** @var array<string, Step> */
    private array $steps = [];

    /** @var array<string, int> runtime priority overrides set via before()/after() */
    private array $priorityOverrides = [];

    public function register(Step $step): self
    {
        $this->steps[$step->key()] = $step;

        return $this;
    }

    /**
     * @param  iterable<Step>  $steps
     */
    public function registerMany(iterable $steps): self
    {
        foreach ($steps as $step) {
            $this->register($step);
        }

        return $this;
    }

    public function before(string $key, Step $step): self
    {
        $this->register($step);
        $this->priorityOverrides[$step->key()] = $this->priorityOf($key) - 1;

        return $this;
    }

    public function after(string $key, Step $step): self
    {
        $this->register($step);
        $this->priorityOverrides[$step->key()] = $this->priorityOf($key) + 1;

        return $this;
    }

    public function remove(string $key): self
    {
        unset($this->steps[$key], $this->priorityOverrides[$key]);

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->steps[$key]);
    }

    public function get(string $key): ?Step
    {
        return $this->steps[$key] ?? null;
    }

    /**
     * All registered steps, ordered (regardless of enabled state).
     *
     * @return list<Step>
     */
    public function all(): array
    {
        $steps = array_values($this->steps);

        usort($steps, fn (Step $a, Step $b): int => $this->priorityOf($a->key()) <=> $this->priorityOf($b->key()));

        return $steps;
    }

    /**
     * Enabled steps only, ordered.
     *
     * @return list<Step>
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (Step $step): bool => $step->isEnabled()));
    }

    /**
     * A NEW registry containing only $keys (reusing the registered Step instances),
     * in the **declared order** — priority is derived from list position unless an
     * explicit priority is given for a key. Unknown keys are skipped. Used by the
     * per-product orchestrator ({@see InstallerEngine::forProduct}).
     *
     * @param  list<string>  $keys
     * @param  array<string, int>  $priorities
     */
    public function select(array $keys, array $priorities = []): self
    {
        $selected = new self;
        $position = 0;

        foreach ($keys as $key) {
            $step = $this->steps[$key] ?? null;

            if ($step === null) {
                continue;
            }

            // Advance position for every selected key so positional priorities keep
            // the declared order even when some keys carry an explicit priority
            // (an explicit priority is absolute and overrides position).
            $position++;
            $selected->register($step);
            $selected->priorityOverrides[$key] = $priorities[$key] ?? ($position * 10);
        }

        return $selected;
    }

    /**
     * The enabled step following $currentKey (or the first enabled step when null).
     */
    public function next(?string $currentKey): ?Step
    {
        $enabled = $this->enabled();

        if ($currentKey === null) {
            return $enabled[0] ?? null;
        }

        foreach ($enabled as $i => $step) {
            if ($step->key() === $currentKey) {
                return $enabled[$i + 1] ?? null;
            }
        }

        return null;
    }

    private function priorityOf(string $key): int
    {
        if (array_key_exists($key, $this->priorityOverrides)) {
            return $this->priorityOverrides[$key];
        }

        $step = $this->steps[$key] ?? throw new InstallerException("Unknown installer step [{$key}].");

        return $step->priority();
    }
}
