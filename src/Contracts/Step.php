<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Contracts;

use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * A single unit of the install pipeline / wizard. The same `run()` is the one
 * entry point both the web layer and the CLI invoke — all step logic lives here
 * (there is no separate "action" layer), so behavior cannot drift between front
 * ends. A step also declares its own input fields and validation rules once, so
 * both front ends render and validate from a single source.
 */
interface Step
{
    /** Stable identifier (e.g. "environment"); used in config, state and routing. */
    public function key(): string;

    /** Human label (may be a translation key resolved by the caller). */
    public function label(): string;

    /** Lower runs earlier. Default from config, overridable per step. */
    public function priority(): int;

    /** Whether this step participates in the current run. */
    public function isEnabled(): bool;

    /**
     * Input fields this step collects (empty for fieldless steps).
     *
     * @return list<Field>
     */
    public function fields(): array;

    /**
     * Laravel validation rules for the visible fields, given current input.
     * The single validation source consumed by both CLI and web.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function rules(array $input = []): array;

    /** Execute the step. Must be idempotent / safe to re-run. */
    public function run(InstallerContext $context): void;
}
