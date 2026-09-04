<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/installer-token-' . uniqid();
    mkdir($this->dir, 0755, true);
    file_put_contents($this->dir . '/.env', "APP_NAME=Example\n");
    config()->set('installer.env.path', $this->dir . '/.env');
});

afterEach(function (): void {
    @unlink($this->dir . '/.env');
    @rmdir($this->dir);
});

it('writes a raw installer token to .env with --write', function (): void {
    $this->artisan('laranail::installer.token', ['--write' => true])->assertSuccessful();

    expect(file_get_contents($this->dir . '/.env'))->toContain('INSTALLER_TOKEN=');
});

it('writes a hashed installer token with --hash', function (): void {
    $this->artisan('laranail::installer.token', ['--hash' => true])->assertSuccessful();

    expect(file_get_contents($this->dir . '/.env'))->toContain('INSTALLER_TOKEN_HASH=');
});

it('prints the token without writing by default', function (): void {
    $this->artisan('laranail::installer.token')->assertSuccessful();

    expect(file_get_contents($this->dir . '/.env'))->not->toContain('INSTALLER_TOKEN');
});
