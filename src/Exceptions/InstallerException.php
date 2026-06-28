<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Exceptions;

use RuntimeException;

/**
 * Base exception for all installer engine failures. Catch this to handle any
 * installer error generically; catch a subclass for a specific failure mode.
 */
class InstallerException extends RuntimeException {}
