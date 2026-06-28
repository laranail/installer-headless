<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

/**
 * Carries data across steps for a single installation run.
 *
 * `input` is user-supplied data collected by a front end (web form fields, CLI
 * options/answers); `options` are run flags (e.g. force, no-interaction); `data`
 * is values produced by earlier steps for later ones. The same context object is
 * driven identically by the web layer, the CLI and the engine.
 */
final class InstallerContext
{
    private ?ProductPipeline $product = null;

    private ?InstallationState $state = null;

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private array $input = [],
        private array $options = [],
        private array $data = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, array $options = []): self
    {
        return new self($input, $options);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allInput(): array
    {
        return $this->input;
    }

    public function setInput(string $key, mixed $value): self
    {
        $this->input[$key] = $value;

        return $this;
    }

    /**
     * Replace the whole input set (used after per-step transform stages run).
     *
     * @param  array<string, mixed>  $input
     */
    public function replaceInput(array $input): self
    {
        $this->input = $input;

        return $this;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options) && $this->options[$key] !== null && $this->options[$key] !== false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * The product pipeline this run is scoped to (stamped by the engine), or null
     * for the default single-product run.
     */
    public function product(): ?ProductPipeline
    {
        return $this->product;
    }

    public function setProduct(?ProductPipeline $product): self
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Read a value from the scoped product's config (dot-notation), with a fallback.
     * The product-agnostic way for a step to read per-product config without touching
     * global config.
     */
    public function productConfig(string $key, mixed $default = null): mixed
    {
        if (! $this->product instanceof ProductPipeline) {
            return $default;
        }

        return data_get($this->product->config, $key, $default);
    }

    /**
     * The installation state for this run — product-scoped when the engine is
     * scoped. Steps must use this (not an injected singleton) so per-product runs
     * write to the right markers/state.
     */
    public function state(): ?InstallationState
    {
        return $this->state;
    }

    public function setState(?InstallationState $state): self
    {
        $this->state = $state;

        return $this;
    }
}
