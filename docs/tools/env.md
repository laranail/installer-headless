# Environment files (`EnvFile` / `EnvWriter`)

The installer reads and writes `.env` through two small, dependency-free classes.
It supports **updating an existing file**, not just first-time generation.

## `EnvFile` (in-memory, format-preserving)

Line-oriented parser/editor: comments, blank lines, ordering and spacing are kept
byte-for-byte; only the targeted key's value changes. New keys are appended.

```php
use Simtabi\Laranail\Installer\Headless\Support\EnvFile;

$env = EnvFile::fromString($contents);
$env->get('APP_NAME');           // decoded (quotes/escapes resolved)
$env->has('DB_HOST');
$env->set('DB_HOST', 'db.internal')->set('APP_DEBUG', 'false');
$env->unset('LEGACY_KEY');
$rendered = (string) $env;        // re-serialized, comments intact
```

Values with whitespace/special characters are double-quoted and escaped
(phpdotenv-compatible); simple values are written bare.

## `EnvWriter` (atomic I/O)

```php
use Simtabi\Laranail\Installer\Headless\Support\EnvWriter;

$writer = new EnvWriter;
$writer->update('/path/.env', ['DB_HOST' => 'db.internal']);     // edit in place
$writer->generate('/path/.env.example', '/path/.env', $values);  // first write
```

Writes are atomic: content goes to a temp file in the same directory, is given
`0600` permissions, then renamed over the destination — a crash can never corrupt
the existing file.

## Web & CLI

- Web: the Livewire environment form pre-fills from the existing `.env` and submits
  to the engine, which performs the write.
- CLI: `php artisan laranail::installer.env KEY value`.

[← Docs index](../../README.md#documentation)
