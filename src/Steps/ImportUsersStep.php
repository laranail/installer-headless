<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Steps;

use Override;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Users\UserAccountCreator;
use Simtabi\Laranail\Installer\Headless\Users\UserData;
use Simtabi\Laranail\Installer\Headless\Wizard\Field;

/**
 * Bulk-imports users from a CSV file (header row → columns) or an array, reusing the
 * full user-creation lifecycle (hooks + role resolution), idempotent by email.
 *
 * Off by default; enable via `installer.steps.import-users.enabled` or
 * `Installer::step(new ImportUsersStep)`. Source: the `path` field / config
 * `installer.users.import.path` (CSV), or `installer.users.import.rows` (array).
 *
 * Security: a CSV with plaintext passwords is sensitive — delete it after install
 * (e.g. add it to `installer.cleanup.files`).
 */
class ImportUsersStep extends AbstractStep
{
    protected string $key = 'import-users';

    protected int $defaultPriority = 55;

    private readonly UserAccountCreator $creator;

    public function __construct(?UserAccountCreator $creator = null)
    {
        $this->creator = $creator ?? app(UserAccountCreator::class);
    }

    #[Override]
    protected function stepFields(): array
    {
        return [
            new Field('path', 'Users CSV path', 'text', (string) config('installer.users.import.path', ''), ['nullable', 'string']),
        ];
    }

    public function run(InstallerContext $context): void
    {
        $this->raiseTimeLimit();

        // Stream rows and create one at a time (idempotent by email) — bounded memory
        // for large CSVs, and a timed-out/retried run safely skips existing users.
        $count = 0;

        foreach ($this->rows($context) as $row) {
            $this->creator->create(UserData::fromArray($row));
            $count++;
        }

        if ($count > 0) {
            $context->set('imported_users', $count);
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function rows(InstallerContext $context): iterable
    {
        /** @var list<array<string, mixed>> $configured */
        $configured = (array) config('installer.users.import.rows', []);

        if ($configured !== []) {
            yield from array_values($configured);

            return;
        }

        $path = (string) ($context->input('path') ?? config('installer.users.import.path', ''));

        if ($path !== '' && is_file($path)) {
            yield from $this->parseCsv($path);
        }
    }

    /**
     * Lazily parse a CSV whose first row is the header into row maps.
     *
     * @return iterable<array<string, mixed>>
     */
    private function parseCsv(string $path): iterable
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            $header = fgetcsv($handle, escape: '\\');

            if (! is_array($header)) {
                return;
            }

            $header = array_map(static fn (mixed $h): string => is_string($h) ? trim($h) : (string) $h, $header);

            while (($data = fgetcsv($handle, escape: '\\')) !== false) {
                if ($data === [null]) {
                    continue; // blank line
                }

                $values = array_pad(array_slice($data, 0, count($header)), count($header), null);

                yield array_combine($header, $values);
            }
        } finally {
            fclose($handle);
        }
    }
}
