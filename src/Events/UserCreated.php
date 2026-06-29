<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Simtabi\Laranail\Installer\Headless\Users\UserData;

/**
 * Fired after a user account is created by the installer's user-account creator —
 * for the single user step, each typed user step, and every row of a bulk import.
 *
 * The listenable/queueable counterpart to the `created` creation hook: use it to
 * grant panel access, send a welcome mail, etc., without registering a hook.
 */
final readonly class UserCreated
{
    use Dispatchable;

    public function __construct(public object $user, public UserData $data) {}
}
