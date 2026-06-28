<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

/**
 * Registry of product pipelines. Lazily seeded from `config('installer.products')`
 * on first use; consumers add or override pipelines at runtime from their service
 * provider's boot(). Product-agnostic — nothing product-specific is baked in.
 */
final class ProductRegistry
{
    /** @var array<string, ProductPipeline>|null */
    private ?array $pipelines = null;

    public function register(ProductPipeline $pipeline): self
    {
        $this->boot();
        $this->pipelines[$pipeline->slug] = $pipeline;

        return $this;
    }

    public function get(?string $slug): ?ProductPipeline
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $this->boot();

        return $this->pipelines[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        $this->boot();

        return isset($this->pipelines[$slug]);
    }

    /**
     * @return list<ProductPipeline>
     */
    public function all(): array
    {
        $this->boot();

        return array_values($this->pipelines ?? []);
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        $this->boot();

        return array_keys($this->pipelines ?? []);
    }

    private function boot(): void
    {
        if ($this->pipelines !== null) {
            return;
        }

        $this->pipelines = [];

        foreach ((array) config('installer.products', []) as $slug => $def) {
            if (is_array($def)) {
                $this->pipelines[(string) $slug] = ProductPipeline::fromConfig((string) $slug, $def);
            }
        }
    }
}
