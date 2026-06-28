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
            $results[$path] = is_writable(base_path($path));
        }

        return $results;
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

        $passes = $php['passes']
            && ! in_array(false, $extensions, true)
            && ! in_array(false, $permissions, true);

        return [
            'php' => $php,
            'extensions' => $extensions,
            'optional' => $optional,
            'apache' => $apache,
            'permissions' => $permissions,
            'passes' => $passes,
        ];
    }
}
