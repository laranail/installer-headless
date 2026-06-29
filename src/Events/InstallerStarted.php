<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once when installation begins (the first time the "installing" marker is
 * set). Carries the product slug when running a product-scoped pipeline.
 */
final readonly class InstallerStarted
{
    use Dispatchable;

    public function __construct(public ?string $product = null) {}
}
