<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Steps\AbstractStep;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\ProductPipeline;
use Simtabi\Laranail\Installer\Headless\Support\ProductRegistry;

/** Captures what a step sees, to assert product context/overlay reach it. */
final class RecordingProductStep extends AbstractStep
{
    /** @var array<string, mixed> */
    public static array $seen = [];

    protected string $key = 'recording';

    protected int $defaultPriority = 5;

    public function run(InstallerContext $context): void
    {
        self::$seen = [
            'product' => $context->product()?->slug,
            'context_seeder' => $context->productConfig('database.seeder'),
            'overlay_seeder' => config('installer.database.seeder'),
        ];
    }
}

function orderedKeys(InstallerEngine $engine, ?string $product = null): array
{
    return array_map(static fn (Step $s): string => $s->key(), $engine->forProduct($product)->orderedSteps());
}

it('runs only the product pipeline steps, in declared order, including globally-disabled ones', function (): void {
    // `license` is disabled by default; selecting it for a product still runs it.
    config()->set('installer.products.addon', ['steps' => ['license', 'requirements']]);

    expect(orderedKeys(app(InstallerEngine::class), 'addon'))->toBe(['license', 'requirements']);
});

it('falls back to the default pipeline for an unknown or product-less engine', function (): void {
    $default = array_map(static fn (Step $s): string => $s->key(), app(InstallerEngine::class)->orderedSteps());

    expect(orderedKeys(app(InstallerEngine::class), 'nope'))->toBe($default)
        ->and(orderedKeys(app(InstallerEngine::class)))->toBe($default);
});

it('applies InstallType preset steps', function (): void {
    config()->set('installer.products.mod', ['type' => 'module']);

    expect(orderedKeys(app(InstallerEngine::class), 'mod'))->toBe(['requirements', 'migrate', 'license', 'final']);
});

it('supports runtime product registration', function (): void {
    app(ProductRegistry::class)->register(new ProductPipeline('rt', steps: ['requirements', 'final']));

    expect(orderedKeys(app(InstallerEngine::class), 'rt'))->toBe(['requirements', 'final']);
});

it('select() keeps only the given keys in declared order and skips unknown ones', function (): void {
    $registry = app(StepRegistry::class)->select(['final', 'does-not-exist', 'requirements']);
    $keys = array_map(static fn (Step $s): string => $s->key(), $registry->all());

    expect($keys)->toBe(['final', 'requirements']); // declared order, not default priority
});

it('exposes product config to steps via context and the opt-in global overlay (restored after)', function (): void {
    RecordingProductStep::$seen = [];
    app(StepRegistry::class)->register(new RecordingProductStep);

    config()->set('installer.products.cfg', [
        'steps' => ['recording'],
        'config' => ['database' => ['seeder' => 'AddonSeeder']],
        'config_overlay' => true,
    ]);

    app(InstallerEngine::class)->forProduct('cfg')->run(InstallerContext::fromInput([]));

    expect(RecordingProductStep::$seen['product'])->toBe('cfg')
        ->and(RecordingProductStep::$seen['context_seeder'])->toBe('AddonSeeder')
        ->and(RecordingProductStep::$seen['overlay_seeder'])->toBe('AddonSeeder')
        ->and(config('installer.database.seeder'))->not->toBe('AddonSeeder'); // overlay restored

    app(InstallationState::class)->forProduct('cfg')->clear();
});

it('reports a product not installed even when the app itself is (DB heuristics)', function (): void {
    // Simulate an installed app: populated migrations table + APP_KEY (set by TestCase).
    Schema::create('migrations', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
    DB::table('migrations')->insert(['migration' => '2014_01_01_000000_base', 'batch' => 1]);

    $state = app(InstallationState::class);

    expect($state->isInstalled())->toBeTrue()                          // app heuristic ⇒ installed
        ->and($state->forProduct('shop')->isInstalled())->toBeFalse(); // product is marker-only

    Schema::dropIfExists('migrations');
    $state->forProduct('shop')->clear();
});

it('isolates install state per product', function (): void {
    $a = app(InstallationState::class)->forProduct('a');
    $b = app(InstallationState::class)->forProduct('b');
    $a->clear();
    $b->clear();

    $a->markStepComplete('requirements');

    expect($a->isStepComplete('requirements'))->toBeTrue()
        ->and($b->isStepComplete('requirements'))->toBeFalse();

    $a->clear();
    $b->clear();
});
