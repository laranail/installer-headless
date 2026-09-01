<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Users\UserFormHooks;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

it('adds config-driven flat extra fields to the admin form', function (): void {
    config()->set('installer.user.form_fields', [
        ['name' => 'company', 'label' => 'Company', 'rules' => ['required', 'string']],
    ]);

    $names = array_map(fn (Field $f): string => $f->name, app(UserFormHooks::class)->resolveFields('admin'));

    expect($names)->toContain('company');
});

it('resolves role-keyed config fields (role + common), skipping other roles', function (): void {
    config()->set('installer.user.form_fields', [
        '*' => [['name' => 'phone', 'label' => 'Phone']],
        'admin' => [['name' => 'department', 'label' => 'Department']],
        'editor' => [['name' => 'bio', 'label' => 'Bio']],
    ]);

    $names = array_map(fn (Field $f): string => $f->name, app(UserFormHooks::class)->resolveFields('admin'));

    expect($names)->toContain('phone')
        ->and($names)->toContain('department')
        ->and($names)->not->toContain('bio');
});

it('merges runtime field providers and de-duplicates by name', function (): void {
    config()->set('installer.user.form_fields', [
        ['name' => 'company', 'label' => 'Company', 'rules' => ['nullable']],
    ]);

    app(UserFormHooks::class)
        ->fields(fn (?string $role, array $ctx): array => [
            new Field('company', 'Company (required)', 'text', '', ['required', 'string']),
            new Field('vat', 'VAT', 'text'),
        ]);

    $fields = collect(app(UserFormHooks::class)->resolveFields('admin'))->keyBy(fn (Field $f): string => $f->name);

    expect($fields)->toHaveKey('vat')
        // the runtime provider wins for the duplicate name.
        ->and($fields['company']->rules)->toBe(['required', 'string']);
});

it('exposes the extra fields through the admin step and validates them via the single rule source', function (): void {
    config()->set('installer.user.form_fields', [
        ['name' => 'company', 'label' => 'Company', 'rules' => ['required', 'string', 'max:50']],
    ]);

    $engine = app(InstallerEngine::class);
    $rules = $engine->rules('user', ['name' => 'A', 'email' => 'a@b.c', 'password' => 'secret12', 'password_confirmation' => 'secret12']);

    expect($rules)->toHaveKey('company')
        ->and($rules['company'])->toBe(['required', 'string', 'max:50']);
});

it('never lets an extra field shadow a core field (e.g. password)', function (): void {
    config()->set('installer.user.form_fields', [
        ['name' => 'password', 'label' => 'Pwn', 'rules' => ['nullable']], // reserved — must be dropped
        ['name' => 'company', 'label' => 'Company'],
    ]);

    $step = app(StepRegistry::class)->get('user');
    $names = array_map(static fn (Field $f): string => $f->name, $step->fields());

    // exactly one password field (the core one) and the legit extra survives.
    expect(array_count_values($names)['password'])->toBe(1)
        ->and($names)->toContain('company');

    // the core password rules are intact (not weakened to ['nullable']).
    expect(app(InstallerEngine::class)->rules('user', []))->toHaveKey('password')
        ->and(app(InstallerEngine::class)->rules('user', [])['password'])->toContain('confirmed');
});
