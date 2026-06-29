<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

/**
 * Evaluates server requirements (PHP version, extensions, Apache modules and
 * writable paths) from the `installer.requirements` config. Required extensions
 * and writable paths block installation; optional extensions and Apache modules
 * are reported but never block.
 */
final class RequirementsChecker
{
    /**
     * @return array{required: string, current: string, passes: bool}
     */
    public function checkPhpVersion(?string $minimum = null): array
    {
        $minimum ??= (string) config('installer.requirements.php', PHP_VERSION);

        return [
            'required' => $minimum,
            'current' => PHP_VERSION,
            'passes' => version_compare(PHP_VERSION, $minimum, '>='),
        ];
    }

    /**
     * @param  list<string>  $extensions
     * @return array<string, bool>
     */
    public function checkExtensions(array $extensions): array
    {
        $results = [];

        foreach ($extensions as $extension) {
            $results[$extension] = extension_loaded($extension);
        }

        return $results;
    }

    /**
     * @param  list<string>  $modules
     * @return array<string, bool|null> null = cannot be determined (non-Apache SAPI)
     */
    public function checkApacheModules(array $modules): array
    {
        $loaded = function_exists('apache_get_modules') ? apache_get_modules() : null;

        $results = [];

        foreach ($modules as $module) {
            $results[$module] = $loaded === null ? null : in_array($module, $loaded, true);
        }

        return $results;
    }

    /**
     * @param  list<string>  $paths  paths relative to base_path()
     * @return array<string, bool>
     */
    public function checkPermissions(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $full = base_path($path);

            // For a not-yet-created file (e.g. .env on a fresh install), the relevant
            // test is whether its parent directory is writable — not the absent file.
            $results[$path] = file_exists($full) ? is_writable($full) : is_writable(\dirname($full));
        }

        return $results;
    }

    /**
     * Non-blocking advisories for restricted/shared hosts. Each entry is
     * `[ok, detail]`; `ok=false` is a warning to surface, never a hard failure.
     *
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function warnings(): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $blocked = array_values(array_intersect(['proc_open', 'exec', 'symlink'], $disabled));

        $time = (int) ini_get('max_execution_time');
        $memory = $this->bytes((string) ini_get('memory_limit'));
        $session = (string) config('session.driver');
        $cache = (string) config('cache.default');

        return [
            'disable_functions' => [
                'ok' => true,
                'detail' => $blocked === [] ? 'none' : implode(', ', $blocked) . ' disabled (installer does not require them)',
            ],
            'max_execution_time' => [
                'ok' => $time === 0 || $time >= 30,
                'detail' => $time === 0 ? 'unlimited' : $time . 's (raise it for large migrations/imports)',
            ],
            'memory_limit' => [
                'ok' => $memory < 0 || $memory >= 128 * 1024 * 1024,
                'detail' => (string) ini_get('memory_limit'),
            ],
            'session_driver' => [
                'ok' => ! $this->dbBacked($session),
                'detail' => $session . ($this->dbBacked($session) ? ' — overridden to file during install' : ''),
            ],
            'cache_store' => [
                'ok' => ! $this->dbBacked($cache),
                'detail' => $cache . ($this->dbBacked($cache) ? ' — overridden to file during install' : ''),
            ],
        ];
    }

    private function dbBacked(string $driver): bool
    {
        return in_array($driver, ['database', 'redis'], true);
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Aggregate report. `passes` is true only when PHP, all required
     * extensions and all writable paths are satisfied.
     *
     * @return array{
     *     php: array{required:string,current:string,passes:bool},
     *     extensions: array<string,bool>,
     *     optional: array<string,bool>,
     *     apache: array<string,bool|null>,
     *     permissions: array<string,bool>,
     *     warnings: array<string,array{ok:bool,detail:string}>,
     *     passes: bool
     * }
     */
    public function all(): array
    {
        $requirements = (array) config('installer.requirements', []);

        $php = $this->checkPhpVersion($requirements['php'] ?? null);
        $extensions = $this->checkExtensions($requirements['extensions'] ?? []);
        $optional = $this->checkExtensions($requirements['optional'] ?? []);
        $apache = $this->checkApacheModules($requirements['apache'] ?? []);
        $permissions = $this->checkPermissions($requirements['permissions'] ?? []);

        // Only PHP, required extensions and writable paths gate the install; warnings
        // (limits, disabled functions, db-backed stores) are advisory, never blocking.
        $passes = $php['passes']
            && ! in_array(false, $extensions, true)
            && ! in_array(false, $permissions, true);

        return [
            'php' => $php,
            'extensions' => $extensions,
            'optional' => $optional,
            'apache' => $apache,
            'permissions' => $permissions,
            'warnings' => $this->warnings(),
            'passes' => $passes,
        ];
    }
}
