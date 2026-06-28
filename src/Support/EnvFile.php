<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Stringable;

/**
 * In-memory, format-preserving editor for a dotenv (.env) document.
 *
 * Parsing is line-oriented: comments, blank lines and key=value entries are
 * kept as-is, so {@see EnvFile::set()} replaces only the targeted key's value
 * and leaves every other line — including comments, ordering and spacing —
 * byte-for-byte intact. New keys are appended. Values are (de)serialized with
 * phpdotenv-compatible quoting/escaping.
 *
 * This class performs no I/O; {@see EnvWriter} reads/writes files atomically.
 */
final class EnvFile implements Stringable
{
    /**
     * @param  list<array<string, string>>  $lines
     */
    private function __construct(
        /**
         * Parsed lines. Each is either:
         *  - ['type' => 'raw',   'text' => string]
         *  - ['type' => 'entry', 'key' => string, 'prefix' => string, 'value' => string, 'suffix' => string]
         */
        private array $lines
    ) {}

    public static function fromString(string $contents): self
    {
        $lines = [];

        // Preserve the document exactly; split on \n and keep \r if CRLF.
        foreach (explode("\n", $contents) as $raw) {
            $parsed = self::parseLine($raw);
            $lines[] = $parsed;
        }

        // explode() on a trailing newline yields a final empty element; that is
        // the document's trailing newline and we keep it as a blank raw line.
        return new self($lines);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function has(string $key): bool
    {
        return array_any($this->lines, fn (array $line): bool => ($line['type'] ?? '') === 'entry' && $line['key'] === $key);
    }

    /**
     * Get the decoded value of a key (quotes stripped, escapes resolved).
     */
    public function get(string $key, ?string $default = null): ?string
    {
        foreach ($this->lines as $line) {
            if (($line['type'] ?? '') === 'entry' && $line['key'] === $key) {
                return $this->decodeValue($line['value']);
            }
        }

        return $default;
    }

    /**
     * @return array<string, string> decoded key => value pairs
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->lines as $line) {
            if (($line['type'] ?? '') === 'entry') {
                $out[$line['key']] = $this->decodeValue($line['value']);
            }
        }

        return $out;
    }

    /**
     * Set (or insert) a key. Existing keys keep their position, prefix and any
     * trailing comment/whitespace; only the serialized value changes.
     */
    public function set(string $key, string $value): self
    {
        $encoded = $this->encodeValue($value);

        foreach ($this->lines as $i => $line) {
            if (($line['type'] ?? '') === 'entry' && $line['key'] === $key) {
                $this->lines[$i]['value'] = $encoded;

                return $this;
            }
        }

        $this->lines[] = [
            'type' => 'entry',
            'key' => $key,
            'prefix' => $key . '=',
            'value' => $encoded,
            'suffix' => '',
        ];

        return $this;
    }

    /**
     * Bulk set. Order of insertion follows the array for new keys.
     *
     * @param  array<string, string>  $values
     */
    public function setMany(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function unset(string $key): self
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (array $line): bool => ! (($line['type'] ?? '') === 'entry' && $line['key'] === $key),
        ));

        return $this;
    }

    public function render(): string
    {
        $out = [];

        foreach ($this->lines as $line) {
            $out[] = ($line['type'] ?? '') === 'entry'
                ? $line['prefix'] . $line['value'] . $line['suffix']
                : $line['text'];
        }

        return implode("\n", $out);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * @return array<string, string>
     */
    private static function parseLine(string $raw): array
    {
        $trimmed = ltrim($raw);

        // Blank lines and comments are preserved verbatim.
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return ['type' => 'raw', 'text' => $raw];
        }

        // KEY=VALUE (optionally prefixed by `export `). The value capture keeps
        // the remainder of the line verbatim so quotes/comments round-trip.
        if (preg_match('/^(\s*(?:export\s+)?[A-Za-z_][A-Za-z0-9_]*\s*=)(.*)$/s', $raw, $m) === 1) {
            $key = self::keyFromPrefix($m[1]);

            return [
                'type' => 'entry',
                'key' => $key,
                'prefix' => $m[1],
                'value' => $m[2],
                'suffix' => '',
            ];
        }

        // Anything unrecognized is kept as a raw line (never lost).
        return ['type' => 'raw', 'text' => $raw];
    }

    private static function keyFromPrefix(string $prefix): string
    {
        $key = rtrim($prefix);
        $key = rtrim(substr($key, 0, -1)); // drop trailing '='
        $key = preg_replace('/^export\s+/', '', $key) ?? $key;

        return trim($key);
    }

    /**
     * Decode a stored raw value into its logical string.
     */
    private function decodeValue(string $raw): string
    {
        $value = trim($raw);

        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if (($first === '"' || $first === "'") && str_ends_with($value, $first) && strlen($value) >= 2) {
            $inner = substr($value, 1, -1);

            if ($first === '"') {
                return strtr($inner, [
                    '\\n' => "\n",
                    '\\r' => "\r",
                    '\\t' => "\t",
                    '\\"' => '"',
                    '\\\\' => '\\',
                ]);
            }

            return $inner; // single-quoted: literal
        }

        return $value;
    }

    /**
     * Serialize a logical string for writing. Simple values are written bare;
     * anything with whitespace/special characters is double-quoted and escaped.
     */
    private function encodeValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.\-\/@:]+$/', $value) === 1) {
            return $value;
        }

        $escaped = strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);

        return '"' . $escaped . '"';
    }
}
