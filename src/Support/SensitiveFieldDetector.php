<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Support\Str;

/**
 * Identifies sensitive fields and masks their values so secrets never reach
 * logs, events or review tables. Used wherever the installer surfaces collected
 * input (structured logging, CLI review, events).
 */
final class SensitiveFieldDetector
{
    public const string MASKED_VALUE = '********';

    /** @var list<string> */
    private const array SENSITIVE_NEEDLES = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private',
        'purchase_code',
        'license',
        'salt',
        'passwd',
    ];

    public function isSensitive(string $field): bool
    {
        $field = Str::lower($field);

        return array_any(self::SENSITIVE_NEEDLES, fn (string $needle): bool => str_contains($field, $needle));
    }

    /**
     * Return a copy of the array with sensitive values masked.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function mask(array $values): array
    {
        $masked = [];

        foreach ($values as $key => $value) {
            $masked[$key] = $this->isSensitive((string) $key) ? self::MASKED_VALUE : $value;
        }

        return $masked;
    }
}
