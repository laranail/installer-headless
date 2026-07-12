<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Single source of truth for "is this app installed?" and per-step progress.
 *
 * Detection is layered and conservative (mirrors the proven _old/ guard): a
 * disabled installer counts as installed; an explicit installed-marker wins; an
 * in-progress marker means "installing"; otherwise it falls back to runtime
 * heuristics (live DB + populated migrations table + APP_KEY) so the guard
 * still holds if a marker file is deleted. The computed result is cached per
 * instance; call {@see flush()} after mutating state.
 */
final class InstallationState
{
    private const string STATE_FILE = 'installer.state.json';

    private ?bool $installed = null;

    private ?string $product = null;

    public function __construct(
        private readonly DatabaseConnection $database = new DatabaseConnection,
        private readonly SensitiveFieldDetector $sensitive = new SensitiveFieldDetector,
    ) {}

    /**
     * A state instance scoped to a product — its markers and step/input state are
     * namespaced, so multiple products install/resume independently. null = the
     * default (unscoped) product.
     */
    public function forProduct(?string $product): self
    {
        $clone = clone $this;
        $clone->product = $product;
        $clone->flush();

        return $clone;
    }

    public function isInstalled(): bool
    {
        return $this->installed ??= $this->resolveInstalled();
    }

    public function isInstalling(): bool
    {
        $path = $this->markerPath('installing');

        if (! File::exists($path)) {
            return false;
        }

        $timeout = (int) config('installer.lock.timeout', 30);

        if ($timeout <= 0) {
            return true;
        }

        $startedAt = trim((string) File::get($path));

        try {
            return CarbonImmutable::parse($startedAt)->addMinutes($timeout)->isFuture();
        } catch (Throwable) {
            return true;
        }
    }

    public function hasInstalledMarker(): bool
    {
        return File::exists($this->markerPath('installed'));
    }

    public function isDatabaseReady(): bool
    {
        return $this->database->connected();
    }

    public function hasCriticalTables(): bool
    {
        if (! $this->database->hasTable('migrations')) {
            return false;
        }

        try {
            return DB::table('migrations')->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function hasAppKey(): bool
    {
        return ! in_array(config('app.key'), [null, ''], true);
    }

    public function markInstalling(): void
    {
        File::put($this->markerPath('installing'), CarbonImmutable::now()->toDateTimeString());

        $this->flush();
    }

    public function markInstalled(): void
    {
        File::put($this->markerPath('installed'), CarbonImmutable::now()->toDateTimeString());

        File::delete($this->markerPath('installing'));

        $this->flush();
    }

    /**
     * Remove all installer state (markers + step progress). Dev/reset use only.
     */
    public function clear(): void
    {
        File::delete([
            $this->markerPath('installing'),
            $this->markerPath('installed'),
            $this->statePath(),
        ]);

        $this->flush();
    }

    /**
     * Purge the persisted per-step input (captured form values), leaving install
     * markers intact. Used after a successful install so no collected credentials/
     * secrets linger in the state file.
     */
    public function clearInput(): void
    {
        $state = $this->readState();
        unset($state['input']);

        $this->writeState($state);
    }

    public function markStepComplete(string $step): void
    {
        $state = $this->readState();
        $state['completed'][$step] = CarbonImmutable::now()->toIso8601String();

        $this->writeState($state);
    }

    public function isStepComplete(string $step): bool
    {
        return array_key_exists($step, $this->readState()['completed'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function completedSteps(): array
    {
        return array_keys($this->readState()['completed'] ?? []);
    }

    /**
     * Persist an arbitrary selection/value across the install run (e.g. chosen
     * locale or option). Stored alongside step progress.
     */
    public function remember(string $key, mixed $value): void
    {
        $state = $this->readState();
        $state['data'][$key] = $value;

        $this->writeState($state);
    }

    public function recall(string $key, mixed $default = null): mixed
    {
        return $this->readState()['data'][$key] ?? $default;
    }

    /**
     * Persist a step's collected input for resume / back-forward re-hydration.
     *
     * Disabled when `installer.wizard.persist_input` is false. By default
     * sensitive fields are dropped (re-entered each visit); when
     * `installer.wizard.persist_secrets` is true the whole payload is stored
     * encrypted at rest.
     *
     * @param  array<string, mixed>  $input
     */
    public function rememberInput(string $step, array $input): void
    {
        if (! config('installer.wizard.persist_input', true)) {
            return;
        }

        $state = $this->readState();

        if (config('installer.wizard.persist_secrets', false)) {
            $state['input'][$step] = ['enc' => true, 'data' => Crypt::encryptString((string) json_encode($input))];
        } else {
            $clean = [];

            foreach ($input as $key => $value) {
                if (! $this->sensitive->isSensitive((string) $key)) {
                    $clean[$key] = $value;
                }
            }

            $state['input'][$step] = ['enc' => false, 'data' => $clean];
        }

        $this->writeState($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function recallInput(string $step): array
    {
        $entry = $this->readState()['input'][$step] ?? null;

        if (! is_array($entry)) {
            return [];
        }

        if (($entry['enc'] ?? false) === true) {
            try {
                $decoded = json_decode(Crypt::decryptString((string) $entry['data']), true);
            } catch (Throwable) {
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($entry['data'] ?? null) ? $entry['data'] : [];
    }

    public function flush(): void
    {
        $this->installed = null;
    }

    /**
     * Full diagnostic snapshot for the status/doctor command.
     *
     * @return array<string, bool>
     */
    public function status(): array
    {
        return [
            'enabled' => (bool) config('installer.enabled', true),
            'installed' => $this->isInstalled(),
            'installing' => $this->isInstalling(),
            'installed_marker' => $this->hasInstalledMarker(),
            'database_ready' => $this->isDatabaseReady(),
            'critical_tables' => $this->hasCriticalTables(),
            'app_key' => $this->hasAppKey(),
        ];
    }

    public function markerPath(string $which): string
    {
        $name = (string) config('installer.lock.' . $which, 'installer.' . $which);

        return storage_path($this->scopedName($name));
    }

    /**
     * Namespace a marker/state filename by the current product (if any), so
     * per-product state never collides: `installer.installing` →
     * `installer.{product}.installing`.
     */
    private function scopedName(string $name): string
    {
        if ($this->product === null || $this->product === '') {
            return $name;
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $this->product) ?? $this->product;

        return preg_replace('/^installer\./', "installer.{$safe}.", $name, 1) ?? "{$safe}.{$name}";
    }

    private function resolveInstalled(): bool
    {
        if (! config('installer.enabled', true)) {
            return true;
        }

        if ($this->hasInstalledMarker()) {
            return true;
        }

        if ($this->isInstalling()) {
            return false;
        }

        // The DB / app-key heuristics describe the APP, not an individual product —
        // a product is "installed" only by its own marker, so further products can
        // still be installed on an already-migrated app.
        if ($this->product !== null && $this->product !== '') {
            return false;
        }

        return $this->isDatabaseReady() && $this->hasCriticalTables() && $this->hasAppKey();
    }

    private function statePath(): string
    {
        return storage_path($this->scopedName(self::STATE_FILE));
    }

    /**
     * @return array{completed?: array<string, string>, data?: array<string, mixed>, input?: array<string, mixed>}
     */
    private function readState(): array
    {
        $path = $this->statePath();

        if (! File::exists($path)) {
            return ['completed' => []];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : ['completed' => []];
    }

    /**
     * @param  array{completed?: array<string, string>, data?: array<string, mixed>, input?: array<string, mixed>}  $state
     */
    private function writeState(array $state): void
    {
        File::put(
            $this->statePath(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }
}
