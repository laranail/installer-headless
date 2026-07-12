<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Simtabi\Laranail\Installer\Headless\Exceptions\EnvironmentException;

/**
 * Reads and writes .env files for the installer.
 *
 * All writes are atomic: content is written to a temporary file in the target
 * directory, fsync'd, given restrictive 0600 permissions, then renamed over the
 * destination — so a crash or partial write can never corrupt an existing file.
 * Editing preserves comments/ordering/formatting via {@see EnvFile}.
 */
final class EnvWriter
{
    public function read(string $path): EnvFile
    {
        if (! is_file($path)) {
            return EnvFile::empty();
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw EnvironmentException::unreadable($path);
        }

        return EnvFile::fromString($contents);
    }

    public function save(string $path, EnvFile $file): void
    {
        $this->writeAtomic($path, $file->render());
    }

    /**
     * Update specific keys in an existing .env (others untouched), atomically.
     *
     * @param  array<string, string>  $values
     */
    public function update(string $path, array $values): EnvFile
    {
        $file = $this->read($path)->setMany($values);

        $this->save($path, $file);

        return $file;
    }

    /**
     * Generate a target .env from an example template plus overrides.
     *
     * @param  array<string, string>  $values
     */
    public function generate(string $examplePath, string $targetPath, array $values = []): EnvFile
    {
        $file = $this->read($examplePath)->setMany($values);

        $this->save($targetPath, $file);

        return $file;
    }

    private function writeAtomic(string $path, string $contents): void
    {
        $directory = \dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw EnvironmentException::unwritable($path);
        }

        $temp = @tempnam($directory, '.env-');

        if ($temp === false) {
            throw EnvironmentException::unwritable($path);
        }

        $handle = @fopen($temp, 'wb');

        if ($handle === false) {
            @unlink($temp);

            throw EnvironmentException::unwritable($path);
        }

        try {
            if (@fwrite($handle, $contents) === false) {
                throw EnvironmentException::unwritable($path);
            }

            @fflush($handle);
        } catch (EnvironmentException $exception) {
            @fclose($handle);
            @unlink($temp);

            throw $exception;
        }

        @fclose($handle);
        @chmod($temp, 0600);

        if (@rename($temp, $path)) {
            return;
        }

        // Fallback for restricted hosts where renaming over an existing .env is
        // blocked (e.g. the file is owned by another user): copy in place, then
        // drop the temp file. Non-atomic, but it keeps the installer working.
        if (@copy($temp, $path)) {
            @unlink($temp);
            @chmod($path, 0600);

            return;
        }

        @unlink($temp);

        throw EnvironmentException::unwritable($path);
    }
}
