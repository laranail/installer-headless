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
        $rows = $this->rows($context);

        if ($rows === []) {
            return;
        }

        $created = $this->creator->createMany(array_map(
            UserData::fromArray(...),
            $rows,
        ));

        $context->set('imported_users', count($created));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(InstallerContext $context): array
    {
        /** @var list<array<string, mixed>> $configured */
        $configured = (array) config('installer.users.import.rows', []);

        if ($configured !== []) {
            return array_values($configured);
        }

        $path = (string) ($context->input('path') ?? config('installer.users.import.path', ''));

        return $path !== '' && is_file($path) ? $this->parseCsv($path) : [];
    }

    /**
     * Parse a CSV whose first row is the header into row maps.
     *
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            $header = fgetcsv($handle, escape: '\\');

            if (! is_array($header)) {
                return [];
            }

            $header = array_map(static fn (mixed $h): string => is_string($h) ? trim($h) : (string) $h, $header);
            $rows = [];

            while (($data = fgetcsv($handle, escape: '\\')) !== false) {
                if ($data === [null]) {
                    continue; // blank line
                }

                $values = array_pad(array_slice($data, 0, count($header)), count($header), null);
                $rows[] = array_combine($header, $values);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
