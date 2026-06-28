<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal Eloquent user model for tests (stands in for the host app's User).
 */
final class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
