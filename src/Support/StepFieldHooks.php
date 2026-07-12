<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Per-step extra-field registry — lets a consumer add fields to ANY step (rendered,
 * validated, and persisted through the same wizard {@see Field} path), from config
 * (`installer.steps.<key>.fields`) or at runtime via the Installer DSL. Generalises
 * what {@see UserFormHooks} does for the
 * user step to the whole pipeline.
 */
class StepFieldHooks
{
    /** @var array<string, list<callable(array<string, mixed>): iterable<Field>>> */
    private array $providers = [];

    /**
     * Register an extra field (or a provider returning fields) for a step.
     */
    public function add(string $step, Field|callable $field): self
    {
        $this->providers[$step][] = $field instanceof Field
            ? static fn (): array => [$field]
            : $field;

        return $this;
    }

    /**
     * Resolve a step's extra fields (config first, then runtime providers),
     * de-duplicated by field name (later wins).
     *
     * @param  array<string, mixed>  $context
     * @return list<Field>
     */
    public function resolve(string $step, array $context = []): array
    {
        $byName = [];

        foreach ((array) config("installer.steps.{$step}.fields", []) as $def) {
            $field = is_array($def) ? Field::fromArray($def) : null;

            if ($field instanceof Field) {
                $byName[$field->name] = $field;
            }
        }

        foreach ($this->providers[$step] ?? [] as $provider) {
            foreach ($provider($context) as $field) {
                if ($field instanceof Field) {
                    $byName[$field->name] = $field;
                }
            }
        }

        return array_values($byName);
    }
}
