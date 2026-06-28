<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users;

/**
 * Extension points for user-account creation, for consumers that need finer
 * control than the model/field-map config or the full `creator` override.
 *
 * Register callbacks from your service provider's boot():
 *
 *   app(UserCreationHooks::class)
 *       ->preparing(fn (array $attrs, UserData $d) => $attrs + ['tenant_id' => 1])
 *       ->creating(fn (UserData $d) => null)               // return a user to override creation
 *       ->roleAssigning(fn (object $u, string $role) => false) // return true to take over role assignment
 *       ->created(fn (object $u, UserData $d) => activity()->log('user created'));
 */
final class UserCreationHooks
{
    /** @var list<callable(array<string,mixed>, UserData): array<string,mixed>> */
    private array $preparing = [];

    /** @var list<callable(UserData): ?object> */
    private array $creating = [];

    /** @var list<callable(object, string): bool> */
    private array $roleAssigning = [];

    /** @var list<callable(object, UserData): void> */
    private array $created = [];

    public function preparing(callable $callback): self
    {
        $this->preparing[] = $callback;

        return $this;
    }

    public function creating(callable $callback): self
    {
        $this->creating[] = $callback;

        return $this;
    }

    public function roleAssigning(callable $callback): self
    {
        $this->roleAssigning[] = $callback;

        return $this;
    }

    public function created(callable $callback): self
    {
        $this->created[] = $callback;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function runPreparing(array $attributes, UserData $data): array
    {
        foreach ($this->preparing as $callback) {
            $attributes = $callback($attributes, $data);
        }

        return $attributes;
    }

    /** First hook to return a user wins (overrides default creation). */
    public function runCreating(UserData $data): ?object
    {
        foreach ($this->creating as $callback) {
            $user = $callback($data);

            if (is_object($user)) {
                return $user;
            }
        }

        return null;
    }

    /** True if a hook handled role assignment (skip the default driver). */
    public function runRoleAssigning(object $user, string $role): bool
    {
        $handled = false;

        foreach ($this->roleAssigning as $callback) {
            $handled = $callback($user, $role) || $handled;
        }

        return $handled;
    }

    public function runCreated(object $user, UserData $data): void
    {
        foreach ($this->created as $callback) {
            $callback($user, $data);
        }
    }
}
