<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Support\StepFieldHooks;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Base step that resolves enabled/priority from `installer.steps.<key>` config
 * (with code defaults as fallback) and exposes a translation-backed label.
 * Concrete steps set {@see $key} and the default priority, then implement run().
 *
 * Input-collecting steps override {@see fields()}; `rules()` and {@see defaults()}
 * are derived from the visible fields, so the field definition is the single
 * source for both validation and rendering.
 */
abstract class AbstractStep implements Step
{
    use Macroable;

    protected string $key = '';

    protected int $defaultPriority = 100;

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        $line = "installer::installer.steps.{$this->key}";
        $translated = trans($line);

        return $translated === $line ? ucfirst(str_replace(['-', '_'], ' ', $this->key)) : (string) $translated;
    }

    /**
     * Best-effort raise of the PHP execution-time limit for long-running steps
     * (migrations, imports) on hosts with a low `max_execution_time`. No-op when
     * `installer.environment.time_limit` is null or set_time_limit is disabled.
     */
    protected function raiseTimeLimit(): void
    {
        $limit = config('installer.environment.time_limit');

        if ($limit !== null && function_exists('set_time_limit')) {
            @set_time_limit((int) $limit);
        }
    }

    public function priority(): int
    {
        return (int) config("installer.steps.{$this->key}.priority", $this->defaultPriority);
    }

    public function isEnabled(): bool
    {
        return (bool) config("installer.steps.{$this->key}.enabled", true);
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return [...$this->stepFields(), ...$this->resolveExtraFields()];
    }

    /**
     * A step's own fields. Concrete steps override this; the public {@see fields()}
     * appends consumer-registered extra fields ({@see resolveExtraFields()}).
     *
     * @return list<Field>
     */
    protected function stepFields(): array
    {
        return [];
    }

    /**
     * Consumer-registered extra fields for this step (config + runtime hooks).
     *
     * @return list<Field>
     */
    protected function resolveExtraFields(): array
    {
        return app(StepFieldHooks::class)->resolve($this->key());
    }

    /**
     * Rules for the currently-visible fields. Hidden fields contribute none.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function rules(array $input = []): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            if ($field->rules !== [] && $field->isVisible($input)) {
                $rules[$field->name] = $field->rules;
            }
        }

        return $rules;
    }

    /**
     * Default values keyed by field name (for re-hydration / prefilling).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            $defaults[$field->name] = $field->default;
        }

        return $defaults;
    }
}
