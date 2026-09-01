<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Simtabi\Laranail\Installer\Headless\Enums\InstallType;

/**
 * Declarative description of one product's install pipeline: which steps run, in
 * what order/priority, and any per-product config. Single-product is the degenerate
 * case — an empty `steps` list means "the full default pipeline".
 */
final readonly class ProductPipeline
{
    /**
     * @param  list<string>  $steps  ordered step keys ([] = full default pipeline)
     * @param  array<string, int>  $priorities  explicit priority overrides (else order is derived from $steps)
     * @param  array<string, mixed>  $config  per-product config (read via InstallerContext::productConfig)
     * @param  bool  $configOverlay  also merge $config into global installer.* during execution
     */
    public function __construct(
        public string $slug,
        public string $label = '',
        public array $steps = [],
        public array $priorities = [],
        public array $config = [],
        public bool $configOverlay = false,
        public ?InstallType $type = null,
    ) {}

    /**
     * Build from a `config('installer.products.<slug>')` entry. A `type` supplies the
     * default steps/priorities, which explicit `steps`/`priorities` then override.
     *
     * @param  array<string, mixed>  $def
     */
    public static function fromConfig(string $slug, array $def): self
    {
        $type = isset($def['type']) ? InstallType::tryFrom((string) $def['type']) : null;

        $steps = array_values(array_map(strval(...), (array) ($def['steps'] ?? [])));

        if ($steps === [] && $type instanceof InstallType) {
            $steps = $type->defaultSteps();
        }

        /** @var array<string, int> $priorities */
        $priorities = array_map(intval(...), (array) ($def['priorities'] ?? []));

        if ($priorities === [] && $type instanceof InstallType) {
            $priorities = $type->defaultPriorities();
        }

        return new self(
            slug: $slug,
            label: (string) ($def['label'] ?? ucfirst($slug)),
            steps: $steps,
            priorities: $priorities,
            config: (array) ($def['config'] ?? []),
            configOverlay: (bool) ($def['config_overlay'] ?? false),
            type: $type,
        );
    }
}
