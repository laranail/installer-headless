<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Override;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Users\UserAccountCreator;
use Simtabi\Laranail\Installer\Headless\Users\UserData;
use Simtabi\Laranail\Installer\Headless\Users\UserFormHooks;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Creates the first user account from collected input (idempotent).
 *
 * The core fields below are always present; extra per-role/config fields come from
 * {@see UserFormHooks} and are captured into {@see UserData::$extra} so they persist
 * as attributes. Both render and validate through the same wizard {@see Field} path.
 */
class CreateUserStep extends AbstractStep
{
    /**
     * Reserved keys owned by the core fields — extra hook/config fields may never
     * override them (so an extra field named `password` can't overwrite the hashed
     * password with plaintext, nor weaken the core validation rules).
     */
    private const array RESERVED = ['name', 'first_name', 'last_name', 'email', 'password', 'password_confirmation', 'role', 'extra'];

    protected string $key = 'user';

    protected int $defaultPriority = 50;

    private readonly UserAccountCreator $creator;

    private readonly UserFormHooks $formHooks;

    /**
     * Per-instance configurable so consumers can register typed user steps, e.g.
     * `Installer::step(new CreateUserStep(key: 'admin-user', role: 'admin', label: 'Admin'))`.
     * All optional — the shipped default step is `key: 'user'` with deps from the container.
     */
    public function __construct(
        ?string $key = null,
        private readonly ?string $role = null,
        private readonly ?string $label = null,
        ?UserAccountCreator $creator = null,
        ?UserFormHooks $formHooks = null,
    ) {
        if ($key !== null && $key !== '') {
            $this->key = $key;
        }
        $this->creator = $creator ?? app(UserAccountCreator::class);
        $this->formHooks = $formHooks ?? app(UserFormHooks::class);
    }

    #[Override]
    public function label(): string
    {
        return $this->label ?? parent::label();
    }

    #[Override]
    protected function stepFields(): array
    {
        return [
            ...$this->nameFields(),
            new Field('email', 'Email', 'email', '', ['required', 'string', 'email', 'max:255']),
            new Field('password', 'Password', 'password', '', ['required', 'string', 'min:8', 'confirmed'], sensitive: true),
            new Field('password_confirmation', 'Confirm password', 'password', '', sensitive: true),
            ...$this->roleField(),
        ];
    }

    /**
     * Optional in-form role select — enabled by `installer.user.role_field`
     * (`[value => label]`). A core field (not an extra), so it's never reserved-dropped;
     * its value flows to {@see UserData::$role}.
     *
     * @return list<Field>
     */
    private function roleField(): array
    {
        /** @var array<string, string> $options */
        $options = (array) config('installer.user.role_field', []);

        if ($options === []) {
            return [];
        }

        $default = $this->role ?? (string) config('installer.user.role', '');

        return [new Field('role', 'Role', 'select', $default, ['required', 'string'], options: $options)];
    }

    /**
     * One `name` field, or `first_name`+`last_name`, per `installer.user.name_shape`.
     *
     * @return list<Field>
     */
    private function nameFields(): array
    {
        if ((string) config('installer.user.name_shape', 'single') === 'split') {
            return [
                new Field('first_name', 'First name', 'text', '', ['required', 'string', 'max:120']),
                new Field('last_name', 'Last name', 'text', '', ['required', 'string', 'max:120']),
            ];
        }

        return [new Field('name', 'Name', 'text', '', ['required', 'string', 'max:120'])];
    }

    public function run(InstallerContext $context): void
    {
        $input = $this->captureExtras($context->allInput());

        // A typed step's role seeds UserData unless the form already supplied one.
        if ($this->role !== null && (string) ($input['role'] ?? '') === '') {
            $input['role'] = $this->role;
        }

        $user = $this->creator->create(UserData::fromArray($input));

        $context->set($this->key, $user);
    }

    /**
     * Extra fields for the user step: role-based fields from {@see UserFormHooks}
     * plus the generic per-step {@see StepFieldHooks} (via the parent), with reserved
     * core names removed so they can never shadow the core fields' rules or values.
     *
     * @return list<Field>
     */
    #[Override]
    protected function resolveExtraFields(): array
    {
        $byName = [];

        foreach ([...$this->formHooks->resolveFields($this->configuredRole()), ...parent::resolveExtraFields()] as $field) {
            if (! in_array($field->name, self::RESERVED, true)) {
                $byName[$field->name] = $field;
            }
        }

        return array_values($byName);
    }

    /**
     * Move the values of the (non-reserved) hook fields into `extra` so they persist
     * as user attributes; the core name/email/password keys are handled directly.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function captureExtras(array $input): array
    {
        $extra = (array) ($input['extra'] ?? []);

        foreach ($this->resolveExtraFields() as $field) {
            if (array_key_exists($field->name, $input)) {
                $extra[$field->name] = $input[$field->name];
            }
        }

        if ($extra !== []) {
            $input['extra'] = $extra;
        }

        return $input;
    }

    private function configuredRole(): ?string
    {
        if ($this->role !== null && $this->role !== '') {
            return $this->role;
        }

        $role = config('installer.user.role');

        return is_string($role) && $role !== '' ? $role : null;
    }
}
