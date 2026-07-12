<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Exceptions\EnvironmentException;
use Simtabi\Laranail\Installer\Headless\Support\EnvFile;
use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/installer-env-' . uniqid();
    mkdir($this->dir, 0755, true);
});

afterEach(function (): void {
    foreach (array_merge(glob($this->dir . '/*') ?: [], glob($this->dir . '/.*') ?: []) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($this->dir);
});

it('generates a target .env from an example plus overrides', function (): void {
    file_put_contents($this->dir . '/.env.example', "APP_NAME=Example\nDB_CONNECTION=mysql\n");

    $writer = new EnvWriter;
    $writer->generate($this->dir . '/.env.example', $this->dir . '/.env', [
        'APP_NAME' => 'Real App',
        'DB_DATABASE' => 'real_db',
    ]);

    $contents = file_get_contents($this->dir . '/.env');

    expect($contents)->toContain('APP_NAME="Real App"')
        ->and($contents)->toContain('DB_CONNECTION=mysql')
        ->and($contents)->toContain('DB_DATABASE=real_db');
});

it('updates only the targeted keys, preserving comments and others', function (): void {
    file_put_contents(
        $this->dir . '/.env',
        "# header\nAPP_NAME=Old\nAPP_ENV=local\n# db\nDB_HOST=127.0.0.1\n",
    );

    (new EnvWriter)->update($this->dir . '/.env', ['DB_HOST' => 'db.internal']);

    $contents = file_get_contents($this->dir . '/.env');

    expect($contents)->toContain('# header')
        ->and($contents)->toContain('APP_NAME=Old')
        ->and($contents)->toContain('# db')
        ->and($contents)->toContain('DB_HOST=db.internal');
});

it('writes with restrictive 0600 permissions', function (): void {
    (new EnvWriter)->update($this->dir . '/.env', ['KEY' => 'value']);

    $perms = substr(sprintf('%o', fileperms($this->dir . '/.env')), -4);

    expect($perms)->toBe('0600');
});

it('returns an empty file when reading a missing path', function (): void {
    $env = (new EnvWriter)->read($this->dir . '/does-not-exist');

    expect($env->all())->toBe([]);
});

it('throws on an unwritable destination directory without leaving a partial file', function (): void {
    $target = $this->dir . '/missing-subdir/.env';

    expect(fn (): EnvFile => (new EnvWriter)->update($target, ['A' => 'b']))
        ->toThrow(EnvironmentException::class);

    expect(file_exists($target))->toBeFalse();
});
