<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Support;

use Illuminate\Support\Facades\File;

/**
 * Conservative post-install cleanup: deletes only the installer artifact files
 * the consumer explicitly lists in `installer.cleanup.files` (e.g. a bundled
 * `database.sql` seed dump). Defaults to nothing, so it never removes a file the
 * consumer did not opt into.
 */
final class PostInstallCleanup
{
    public function handle(): void
    {
        foreach ((array) config('installer.cleanup.files', []) as $relative) {
            $path = base_path((string) $relative);

            if (File::isFile($path)) {
                File::delete($path);
            }
        }
    }
}
