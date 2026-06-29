<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Simtabi\Laranail\Installer\Headless\Events\UserCreated;
use Simtabi\Laranail\Installer\Headless\Exceptions\InstallerException;
use Throwable;

/**
 * Creates the first user account.
 *
 * Two modes, both config-driven:
 *  - Default: maps {@see UserData} onto the configured Eloquent model using the
 *    `installer.user.fields` column map (works on any schema), hashes the
 *    password, then assigns a role via {@see RoleManager}. Idempotent — an
 *    existing user with the same email is returned, never truncated.
 *  - Override: when `installer.user.creator` is set (callable or invokable
 *    class), creation is fully delegated to it.
 */
final readonly class UserAccountCreator
{
    private RoleManager $roles;

    private UserCreationHooks $hooks;

    public function __construct(?RoleManager $roles = null, ?UserCreationHooks $hooks = null)
    {
        // Resolve from the container when not injected: RoleManager is a Manager
        // (needs the container) and UserCreationHooks is a shared singleton, so
        // runtime-registered hooks apply even when this is constructed directly.
        $this->roles = $roles ?? app(RoleManager::class);
        $this->hooks = $hooks ?? app(UserCreationHooks::class);
    }

    public function create(UserData $data): object
    {
        $creator = config('installer.user.creator');

        $user = $creator !== null
            ? $this->runOverride($creator, $data)
            : $this->createDefault($data);

        // The listenable counterpart to the `created` hook — fires for the default
        // path, the override path, and every row of createMany().
        UserCreated::dispatch($user, $data);

        return $user;
    }

    /**
     * Create many users (bulk import). Each reuses the full creation lifecycle
     * (preparing/creating/role hooks + role resolution) and is idempotent by email.
     *
     * @param  iterable<UserData>  $users
     * @return list<object>
     */
    public function createMany(iterable $users): array
    {
        $created = [];

        foreach ($users as $data) {
            $created[] = $this->create($data);
        }

        return $created;
    }

    private function runOverride(mixed $creator, UserData $data): object
    {
        if (is_string($creator) && class_exists($creator)) {
            $creator = app($creator);
        }

        if (! is_callable($creator)) {
            throw new InstallerException('The configured `installer.user.creator` is not callable.');
        }

        $user = $creator($data);

        if (! is_object($user)) {
            throw new InstallerException('The user creator must return the created user object.');
        }

        return $user;
    }

    private function createDefault(UserData $data): object
    {
        // A `creating` hook fully owns creation — skip the model path entirely.
        $user = $this->hooks->runCreating($data) ?? $this->createViaModel($data);

        $role = $this->resolveRole($data, $user);

        if (is_string($role) && $role !== '' && ! $this->hooks->runRoleAssigning($user, $role)) {
            $this->roles->resolve()->assign($user, $role);
        }

        $this->hooks->runCreated($user, $data);

        return $user;
    }

    /**
     * The role to assign — generic, never assuming "admin":
     *  1. an explicit per-user role (UserData::$role) always wins;
     *  2. else, if `first_user_is_admin` is on and this is the first user, the admin role;
     *  3. else the configured `installer.user.role` (null = assign nothing).
     */
    private function resolveRole(UserData $data, object $user): ?string
    {
        if ($data->role !== null && $data->role !== '') {
            return $data->role;
        }

        if ((bool) config('installer.user.first_user_is_admin', false) && $this->isFirstUser($user)) {
            return (string) config('installer.user.admin_role', 'admin');
        }

        $role = config('installer.user.role');

        return is_string($role) && $role !== '' ? $role : null;
    }

    /** Whether the just-created user is the only row (table-existence guarded). */
    private function isFirstUser(object $user): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        try {
            return $user->newQuery()->count() <= 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function createViaModel(UserData $data): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = (string) config('installer.user.model');

        if (! is_a($modelClass, Model::class, true)) {
            throw new InstallerException("Configured `installer.user.model` [{$modelClass}] is not an Eloquent model.");
        }

        $fields = (array) config('installer.user.fields', []);
        $emailColumn = (string) ($fields['email'] ?? 'email');
        $passwordColumn = (string) ($fields['password'] ?? 'password');

        /** @var Model|null $existing */
        $existing = $modelClass::query()->where($emailColumn, $data->email)->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        $attributes = $this->hooks->runPreparing(array_merge(
            $this->nameAttributes($data, $fields),
            [
                $emailColumn => $data->email,
                $passwordColumn => Hash::make($data->password),
            ],
            (array) config('installer.user.attributes', []),
            $data->extra,
        ), $data);

        $user = new $modelClass;
        $user->forceFill($attributes);
        $user->save();

        return $user;
    }

    /**
     * The name column(s) to write, per `installer.user.name_shape`: a single `name`
     * column, or separate `first_name`/`last_name` columns.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function nameAttributes(UserData $data, array $fields): array
    {
        if ((string) config('installer.user.name_shape', 'single') === 'split') {
            return [
                (string) ($fields['first_name'] ?? 'first_name') => $data->firstName ?? '',
                (string) ($fields['last_name'] ?? 'last_name') => $data->lastName ?? '',
            ];
        }

        return [(string) ($fields['name'] ?? 'name') => $data->name];
    }
}
