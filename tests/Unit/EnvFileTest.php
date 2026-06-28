<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Support\EnvFile;

$sample = <<<'ENV_WRAP'
# Application
APP_NAME="My App"
APP_ENV=local
APP_KEY=

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PASSWORD=
ENV_WRAP;

it('reads values and decodes quotes', function () use ($sample): void {
    $env = EnvFile::fromString($sample);

    expect($env->get('APP_NAME'))->toBe('My App')
        ->and($env->get('APP_ENV'))->toBe('local')
        ->and($env->get('DB_CONNECTION'))->toBe('mysql')
        ->and($env->get('APP_KEY'))->toBe('')
        ->and($env->get('MISSING', 'fallback'))->toBe('fallback')
        ->and($env->has('DB_HOST'))->toBeTrue()
        ->and($env->has('NOPE'))->toBeFalse();
});

it('round-trips an unchanged document byte-for-byte', function () use ($sample): void {
    expect((string) EnvFile::fromString($sample))->toBe($sample);
});

it('updates a key in place without clobbering comments, order or other keys', function () use ($sample): void {
    $result = (string) EnvFile::fromString($sample)
        ->set('DB_CONNECTION', 'pgsql')
        ->set('DB_HOST', 'db.internal');

    $expected = <<<'ENV_WRAP'
    # Application
    APP_NAME="My App"
    APP_ENV=local
    APP_KEY=
    
    # Database
    DB_CONNECTION=pgsql
    DB_HOST=db.internal
    DB_PASSWORD=
    ENV_WRAP;

    expect($result)->toBe($expected);
});

it('appends new keys at the end', function () use ($sample): void {
    $env = EnvFile::fromString($sample)->set('NEW_KEY', 'value');

    expect($env->has('NEW_KEY'))->toBeTrue()
        ->and(str_ends_with((string) $env, 'NEW_KEY=value'))->toBeTrue();
});

it('removes only the targeted key', function () use ($sample): void {
    $env = EnvFile::fromString($sample)->unset('DB_HOST');

    expect($env->has('DB_HOST'))->toBeFalse()
        ->and($env->has('DB_CONNECTION'))->toBeTrue()
        ->and($env->has('DB_PASSWORD'))->toBeTrue();
});

it('quotes and escapes values that need it', function (): void {
    $env = EnvFile::empty()
        ->set('SIMPLE', 'abc-123_DEF.ghi')
        ->set('SPACED', 'hello world')
        ->set('SPECIAL', 'p@ss"w#rd$')
        ->set('NEWLINE', "a\nb");

    expect($env->render())->toContain('SIMPLE=abc-123_DEF.ghi')
        ->and($env->render())->toContain('SPACED="hello world"')
        ->and($env->render())->toContain('SPECIAL="p@ss\\"w#rd$"')
        ->and($env->render())->toContain('NEWLINE="a\\nb"');
});

it('decodes what it encodes (value fidelity)', function (): void {
    $values = ['x' => 'hello world', 'y' => 'quote"inside', 'z' => "line1\nline2", 'w' => 'back\\slash'];

    $env = EnvFile::empty()->setMany($values);
    $reparsed = EnvFile::fromString($env->render());

    foreach ($values as $key => $value) {
        expect($reparsed->get($key))->toBe($value);
    }
});

it('reads single-quoted values literally', function (): void {
    $env = EnvFile::fromString("KEY='no \\n interpolation'");

    expect($env->get('KEY'))->toBe('no \\n interpolation');
});
