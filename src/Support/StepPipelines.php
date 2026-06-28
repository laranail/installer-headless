<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Pipeline\Pipeline;

/**
 * Per-step transform stages. The engine runs a step's collected input through its
 * stages (an {@see Pipeline}) BEFORE validation, so consumers can normalise, enrich
 * or veto input without touching the step. A stage is a class with
 * `handle(array $input, Closure $next): array` or a closure `(array, Closure): array`;
 * not calling `$next` short-circuits the chain.
 */
class StepPipelines
{
    /** @var array<string, list<callable|string>> */
    private array $stages = [];

    public function pipe(string $step, string|callable $stage): self
    {
        $this->stages[$step][] = $stage;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function process(string $step, array $input): array
    {
        $stages = $this->stages[$step] ?? [];

        if ($stages === []) {
            return $input;
        }

        /** @var array<string, mixed> $result */
        $result = app(Pipeline::class)->send($input)->through($stages)->thenReturn();

        return $result;
    }
}
