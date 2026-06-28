<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Console\Commands;

use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Simtabi\Laranail\Console\Progress\ProgressReporter;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Simtabi\Laranail\Installer\Headless\InstallerEngine;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Support\ProductRegistry;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Headless installer — runs the active pipeline non-interactively from flags/env
 * (CI, Docker) or interactively via prompts. Input is **field-driven**: it collects
 * exactly the fields the active pipeline declares (so a product that omits the user
 * step never asks for a user), via `--field=name=value` overrides, friendly aliases,
 * or per-field prompts.
 */
final class InstallCommand extends Command
{
    protected $signature = 'laranail::installer.install
        {--field=* : Set any step field as name=value (repeatable, e.g. --field=first_name=Ada)}
        {--app-name= : Application name}
        {--app-url= : Application URL}
        {--db-driver=mysql : Database driver (mysql, mariadb, pgsql, sqlsrv, sqlite)}
        {--db-host=127.0.0.1 : Database host}
        {--db-port= : Database port}
        {--db-name= : Database name (or sqlite file path)}
        {--db-username= : Database username}
        {--db-password= : Database password}
        {--user-name= : User display name}
        {--user-email= : User email}
        {--user-password= : User password}
        {--locale=en : UI locale}
        {--product= : Install only this product slug (its own pipeline)}
        {--all-products : Install every registered product, each with isolated state}
        {--force : Re-run even if already installed}';

    protected $description = 'Install the application (headless).';

    /** Friendly flag aliases mapped to step field names. */
    private const array ALIASES = [
        'app_name' => 'app-name',
        'app_url' => 'app-url',
        'database_driver' => 'db-driver',
        'database_host' => 'db-host',
        'database_port' => 'db-port',
        'database_name' => 'db-name',
        'database_username' => 'db-username',
        'database_password' => 'db-password',
        'name' => 'user-name',
        'email' => 'user-email',
        'password' => 'user-password',
        'locale' => 'locale',
    ];

    public function handle(InstallerEngine $engine, InstallationState $state, ProductRegistry $products): int
    {
        $force = (bool) $this->option('force');

        foreach ($this->resolveTargets($products) as $slug) {
            $scopedEngine = $engine->forProduct($slug);
            $scopedState = $slug !== null ? $state->forProduct($slug) : $state;
            $label = $slug !== null ? "Installing {$slug}" : 'Installing ' . config('app.name', 'application');

            if ($scopedState->isInstalled() && ! $force) {
                $this->warn(($slug !== null ? "[{$slug}] " : '') . 'Already installed. Use --force to re-run.');

                continue;
            }

            // Collect only the fields THIS pipeline declares (no over-prompting).
            $context = InstallerContext::fromInput($this->collectInput($scopedEngine), ['force' => $force]);

            if (($result = $this->runPipeline($scopedEngine, $scopedState, $context, ! $force, $label)) !== self::SUCCESS) {
                return $result;
            }
        }

        $this->newLine();
        $this->info('Installation complete.');

        return self::SUCCESS;
    }

    /**
     * Which products to install: every registered product (--all-products), one
     * (--product=slug), or the default single pipeline (null).
     *
     * @return list<?string>
     */
    private function resolveTargets(ProductRegistry $products): array
    {
        if ($this->option('all-products')) {
            $slugs = $products->slugs();

            return $slugs === [] ? [null] : array_values($slugs);
        }

        $product = $this->option('product');

        return [is_string($product) && $product !== '' ? $product : null];
    }

    /**
     * Render the live dashboard while running a (possibly product-scoped) pipeline.
     */
    private function runPipeline(InstallerEngine $engine, InstallationState $state, InstallerContext $context, bool $resume, string $label): int
    {
        try {
            // The reporter seam renders via laravel/prompts by default, or the
            // experimental symfony/tui renderer when `console.tui.progress` is on.
            app(ProgressReporter::class)->run(
                $label,
                $engine->orderedSteps(),
                function (Step $step) use ($engine, $state, $context, $resume): string {
                    if ($resume && $state->isStepComplete($step->key())) {
                        return $step->label() . ' (skipped)';
                    }

                    $engine->runStep($step->key(), $context);

                    return $step->label();
                },
            );
        } catch (ValidationException $exception) {
            $this->error('Validation failed:');

            foreach ($exception->validator->errors()->all() as $message) {
                $this->line('  • ' . $message);
            }

            return self::FAILURE;
        } catch (InstallerException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Collect values for exactly the fields the given pipeline declares.
     *
     * @return array<string, mixed>
     */
    private function collectInput(InstallerEngine $engine): array
    {
        $overrides = $this->fieldOverrides();
        $input = [];

        foreach ($engine->orderedSteps() as $step) {
            foreach ($step->fields() as $field) {
                if ($field->name === 'password_confirmation') {
                    continue; // confirmed implicitly below
                }

                $input[$field->name] = $this->valueForField($field, $overrides, $input);
            }
        }

        // The user step's core rules require password confirmation; the CLI confirms
        // implicitly (the operator supplies the password once).
        if (array_key_exists('password', $input)) {
            $input['password_confirmation'] = $input['password'];
        }

        return $input;
    }

    /**
     * @return array<string, string>
     */
    private function fieldOverrides(): array
    {
        $map = [];

        foreach ((array) $this->option('field') as $pair) {
            if (is_string($pair) && str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $map[trim($key)] = $value;
            }
        }

        return $map;
    }

    /**
     * Resolve a field's value: --field override → friendly alias flag → prompt
     * (interactive) → field default.
     *
     * @param  array<string, string>  $overrides
     * @param  array<string, mixed>  $collected
     */
    private function valueForField(Field $field, array $overrides, array $collected): mixed
    {
        if (array_key_exists($field->name, $overrides)) {
            return $overrides[$field->name];
        }

        $alias = self::ALIASES[$field->name] ?? null;

        if ($alias !== null) {
            $value = $this->option($alias);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (! $field->isVisible($collected)) {
            return $field->default;
        }

        return $this->input->isInteractive() ? $this->promptField($field) : $field->default;
    }

    private function promptField(Field $field): mixed
    {
        $required = in_array('required', $field->rules, true);

        return match ($field->type) {
            'password' => password($field->label, required: $required),
            'select' => $field->options !== []
                ? select($field->label, $field->options)
                : text($field->label, required: $required),
            default => text($field->label, default: is_string($field->default) ? $field->default : '', required: $required),
        };
    }
}
