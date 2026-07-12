<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Facades\Installer;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Steps\AbstractStep;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

class PipeRecordStep extends AbstractStep
{
    /** @var array<string, mixed> */
    public static array $seen = [];

    protected string $key = 'pipe-rec';

    protected int $defaultPriority = 1;

    public function run(InstallerContext $context): void
    {
        self::$seen = $context->allInput();
    }
}

function fieldNames(string $step): array
{
    return array_map(static fn (Field $f): string => $f->name, app(InstallerEngine::class)->fields($step));
}

it('adds extra fields to ANY step at runtime via the DSL', function (): void {
    Installer::field('welcome', new Field('newsletter', 'Newsletter', 'checkbox'));

    expect(fieldNames('welcome'))->toContain('newsletter');
});

it('adds extra fields to any step via config, with rules flowing to the single source', function (): void {
    config()->set('installer.steps.welcome.fields', [
        ['name' => 'org', 'label' => 'Organisation', 'rules' => ['required', 'string']],
    ]);

    expect(fieldNames('welcome'))->toContain('org')
        ->and(app(InstallerEngine::class)->rules('welcome', []))->toHaveKey('org');
});

it('still reserves core fields on the user step (no shadowing via generic hooks)', function (): void {
    Installer::field('user', new Field('password', 'Pwn', 'text', '', ['nullable']));
    Installer::field('user', new Field('company', 'Company', 'text'));

    $names = fieldNames('user');

    expect(array_count_values($names)['password'])->toBe(1)
        ->and($names)->toContain('company')
        ->and(app(InstallerEngine::class)->rules('user', [])['password'])->toContain('confirmed');
});

it('runs per-step transform stages before the step executes', function (): void {
    PipeRecordStep::$seen = [];
    Installer::step(new PipeRecordStep);
    Installer::pipe('pipe-rec', fn (array $input, Closure $next): array => $next(['email' => strtoupper((string) ($input['email'] ?? ''))]));

    app(InstallerEngine::class)->runStep('pipe-rec', InstallerContext::fromInput(['email' => 'a@b.c']), validate: false);

    expect(PipeRecordStep::$seen['email'])->toBe('A@B.C');
});
