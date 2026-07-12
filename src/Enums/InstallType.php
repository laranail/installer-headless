<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Enums;

/**
 * Optional convenience presets for a product's default pipeline. A product may set
 * `type` to inherit one of these step sets, then override with explicit `steps` /
 * `priorities`. Presets reference the installer's **actual** step keys; `type` is
 * never required — declaring `steps` directly is the primary, product-agnostic path.
 */
enum InstallType: string
{
    case App = 'app';
    case Module = 'module';
    case Plugin = 'plugin';

    /**
     * Default ordered step keys for this install type.
     *
     * @return list<string>
     */
    public function defaultSteps(): array
    {
        return match ($this) {
            self::App => ['welcome', 'requirements', 'environment', 'migrate', 'user', 'license', 'final'],
            self::Module => ['requirements', 'migrate', 'license', 'final'],
            self::Plugin => ['requirements', 'license', 'final'],
        };
    }

    /**
     * Default priorities derived from the step order (10, 20, 30, …).
     *
     * @return array<string, int>
     */
    public function defaultPriorities(): array
    {
        $priorities = [];

        foreach ($this->defaultSteps() as $index => $key) {
            $priorities[$key] = ($index + 1) * 10;
        }

        return $priorities;
    }
}
