<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users;

use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Per-role, config-and-runtime-driven extra fields for the user-creation form.
 *
 * Complements {@see UserCreationHooks} (which owns the *creation* lifecycle): this
 * shapes the *form* — what extra fields are rendered, validated, and captured for a
 * given role. Extra field values are carried into {@see UserData::$extra} and
 * persisted as attributes (see UserAccountCreator), so no separate plumbing is
 * needed. Built on the same {@see Field} VO the wizard already uses, so the fields
 * render in both the CLI and the web UI and validate from one rule source.
 *
 * Two sources, merged (config first, then runtime providers):
 *  - Config `installer.user.form_fields` — either a flat list of field defs (all
 *    roles) or a role-keyed map (`['admin' => [...], '*' => [...common]]`).
 *  - Runtime providers registered from a service provider's boot():
 *
 *      app(UserFormHooks::class)->fields(fn (?string $role, array $ctx) => [
 *          new Field('company', 'Company', 'text', '', ['required', 'string', 'max:120']),
 *      ]);
 */
final class UserFormHooks
{
    /** @var list<callable(?string, array<string, mixed>): iterable<Field>> */
    private array $providers = [];

    public function fields(callable $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Resolve the extra fields for a role (config + runtime providers), de-duplicated
     * by field name (later definitions win).
     *
     * @param  array<string, mixed>  $context
     * @return list<Field>
     */
    public function resolveFields(?string $role = null, array $context = []): array
    {
        $byName = [];

        foreach ($this->configFields($role) as $field) {
            $byName[$field->name] = $field;
        }

        foreach ($this->providers as $provider) {
            foreach ($provider($role, $context) as $field) {
                if ($field instanceof Field) {
                    $byName[$field->name] = $field;
                }
            }
        }

        return array_values($byName);
    }

    /**
     * @return list<Field>
     */
    private function configFields(?string $role): array
    {
        $defs = (array) config('installer.user.form_fields', []);

        if ($defs === []) {
            return [];
        }

        // A single, un-wrapped field def (`['name' => …, 'label' => …]`) → treat as a
        // one-element flat list rather than a role-keyed map.
        if (isset($defs['name'])) {
            $defs = [$defs];
        } elseif ($this->isRoleKeyed($defs)) {
            $defs = array_merge(
                (array) ($defs['*'] ?? []),
                $role !== null ? (array) ($defs[$role] ?? []) : [],
            );
        }

        $fields = [];

        foreach ($defs as $def) {
            $field = $this->toField($def);

            if ($field instanceof Field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<int|string, mixed>  $defs
     */
    private function isRoleKeyed(array $defs): bool
    {
        return array_keys($defs) !== range(0, count($defs) - 1);
    }

    private function toField(mixed $def): ?Field
    {
        if (! is_array($def) || empty($def['name'])) {
            return null;
        }

        return new Field(
            name: (string) $def['name'],
            label: (string) ($def['label'] ?? $def['name']),
            type: (string) ($def['type'] ?? 'text'),
            default: $def['default'] ?? null,
            rules: array_values((array) ($def['rules'] ?? [])),
            options: (array) ($def['options'] ?? []),
            sensitive: (bool) ($def['sensitive'] ?? false),
            visibleWhen: $this->toVisibleWhen($def['visible_when'] ?? null),
        );
    }

    /**
     * @return array{field: string, in?: list<mixed>, not?: list<mixed>, equals?: mixed}|null
     */
    private function toVisibleWhen(mixed $raw): ?array
    {
        if (! is_array($raw) || ! isset($raw['field'])) {
            return null;
        }

        $visibleWhen = ['field' => (string) $raw['field']];

        if (array_key_exists('in', $raw)) {
            $visibleWhen['in'] = array_values((array) $raw['in']);
        }

        if (array_key_exists('not', $raw)) {
            $visibleWhen['not'] = array_values((array) $raw['not']);
        }

        if (array_key_exists('equals', $raw)) {
            $visibleWhen['equals'] = $raw['equals'];
        }

        return $visibleWhen;
    }
}
