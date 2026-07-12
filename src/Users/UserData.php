<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users;

/**
 * Framework-agnostic value object describing the user account to create.
 * Decouples the user step / events from any particular user model.
 */
final readonly class UserData
{
    /**
     * `name` is always the composed display name (for single-column models);
     * `firstName`/`lastName` are kept for split-column models.
     *
     * @param  array<string, mixed>  $extra  additional attributes to persist
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $role = null,
        public array $extra = [],
        public ?string $firstName = null,
        public ?string $lastName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $first = isset($input['first_name']) ? (string) $input['first_name'] : null;
        $last = isset($input['last_name']) ? (string) $input['last_name'] : null;

        return new self(
            name: (string) ($input['name'] ?? trim(($first ?? '') . ' ' . ($last ?? ''))),
            email: (string) ($input['email'] ?? ''),
            password: (string) ($input['password'] ?? ''),
            role: isset($input['role']) ? (string) $input['role'] : null,
            extra: $input['extra'] ?? [],
            firstName: $first,
            lastName: $last,
        );
    }
}
