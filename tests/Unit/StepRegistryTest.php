<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;

function fakeStep(string $key, int $priority, bool $enabled = true): Step
{
    return new readonly class($key, $priority, $enabled) implements Step
    {
        public function __construct(private string $k, private int $p, private bool $e) {}

        public function key(): string
        {
            return $this->k;
        }

        public function label(): string
        {
            return $this->k;
        }

        public function priority(): int
        {
            return $this->p;
        }

        public function isEnabled(): bool
        {
            return $this->e;
        }

        public function fields(): array
        {
            return [];
        }

        public function rules(array $input = []): array
        {
            return [];
        }

        public function run(InstallerContext $context): void {}
    };
}

it('orders steps by priority', function (): void {
    $registry = (new StepRegistry)
        ->register(fakeStep('c', 30))
        ->register(fakeStep('a', 10))
        ->register(fakeStep('b', 20));

    expect(array_map(fn (Step $s): string => $s->key(), $registry->all()))->toBe(['a', 'b', 'c']);
});

it('excludes disabled steps from the enabled list', function (): void {
    $registry = (new StepRegistry)
        ->register(fakeStep('a', 10))
        ->register(fakeStep('b', 20, enabled: false));

    expect(array_map(fn (Step $s): string => $s->key(), $registry->enabled()))->toBe(['a']);
});

it('inserts a step before and after another', function (): void {
    $registry = (new StepRegistry)
        ->register(fakeStep('a', 10))
        ->register(fakeStep('b', 20))
        ->before('b', fakeStep('pre', 999))
        ->after('a', fakeStep('post', 0));

    expect(array_map(fn (Step $s): string => $s->key(), $registry->all()))->toBe(['a', 'post', 'pre', 'b']);
});

it('replaces a step registered with an existing key', function (): void {
    $registry = (new StepRegistry)
        ->register(fakeStep('a', 10))
        ->register(fakeStep('a', 99));

    expect($registry->all())->toHaveCount(1)
        ->and($registry->get('a')->priority())->toBe(99);
});

it('removes a step', function (): void {
    $registry = (new StepRegistry)
        ->register(fakeStep('a', 10))
        ->register(fakeStep('b', 20))
        ->remove('a');

    expect($registry->has('a'))->toBeFalse()
        ->and($registry->all())->toHaveCount(1);
});

it('resolves the next enabled step', function (): void {
    $registry = (new StepRegistry)
        ->register(fakeStep('a', 10))
        ->register(fakeStep('b', 20))
        ->register(fakeStep('c', 30));

    expect($registry->next(null)->key())->toBe('a')
        ->and($registry->next('a')->key())->toBe('b')
        ->and($registry->next('c'))->toBeNull();
});
