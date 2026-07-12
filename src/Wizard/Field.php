<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Wizard;

/**
 * Framework-neutral description of one input field on a wizard step.
 *
 * A step declares its fields once in core; both the CLI and the web UI render and
 * validate from this single definition. `rules` are plain Laravel rule arrays (the
 * single validation source); `visibleWhen` expresses conditional visibility
 * against other collected input.
 */
final readonly class Field
{
    /**
     * @param  list<mixed>  $rules  Laravel validation rules for this field
     * @param  array<string, string>  $options  value => label, for select fields
     * @param  array{field:string, in?:list<mixed>, not?:list<mixed>, equals?:mixed}|null  $visibleWhen
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public mixed $default = null,
        public array $rules = [],
        public array $options = [],
        public bool $sensitive = false,
        public ?array $visibleWhen = null,
    ) {}

    /**
     * Build a field from a config/array definition (used by the field hooks).
     * Returns null for a malformed def (no `name`).
     *
     * @param  array<string, mixed>  $def
     */
    public static function fromArray(array $def): ?self
    {
        if (empty($def['name'])) {
            return null;
        }

        return new self(
            name: (string) $def['name'],
            label: (string) ($def['label'] ?? $def['name']),
            type: (string) ($def['type'] ?? 'text'),
            default: $def['default'] ?? null,
            rules: array_values((array) ($def['rules'] ?? [])),
            options: (array) ($def['options'] ?? []),
            sensitive: (bool) ($def['sensitive'] ?? false),
            visibleWhen: self::normalizeVisibleWhen($def['visible_when'] ?? null),
        );
    }

    /**
     * @return array{field: string, in?: list<mixed>, not?: list<mixed>, equals?: mixed}|null
     */
    private static function normalizeVisibleWhen(mixed $raw): ?array
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

    /**
     * Whether this field is shown given the current input (conditional visibility).
     *
     * @param  array<string, mixed>  $input
     */
    public function isVisible(array $input): bool
    {
        if ($this->visibleWhen === null) {
            return true;
        }

        $value = $input[$this->visibleWhen['field']] ?? null;

        return match (true) {
            array_key_exists('in', $this->visibleWhen) => in_array($value, $this->visibleWhen['in'], true),
            array_key_exists('not', $this->visibleWhen) => ! in_array($value, $this->visibleWhen['not'], true),
            array_key_exists('equals', $this->visibleWhen) => $value === $this->visibleWhen['equals'],
            default => true,
        };
    }
}
