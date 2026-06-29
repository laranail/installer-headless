<?php

declare(strict_types=1);
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Installer enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When false the installer never serves its routes and the
    | guards/commands treat the app as already installed. Turn off in any
    | environment that must never be (re)installed over the web.
    |
    */

    'enabled' => env('INSTALLER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Post-install redirect
    |--------------------------------------------------------------------------
    |
    | Where to send the browser once installation finishes (and where the
    | "already installed" guard redirects to). A route name or a URL/path.
    |
    */

    'redirect_to' => env('INSTALLER_REDIRECT_TO', '/'),

    /*
    |--------------------------------------------------------------------------
    | Access lockdown
    |--------------------------------------------------------------------------
    |
    | Restrict who/when/how the web installer can be reached. Every control is
    | OFF by default (empty/false) so a default install behaves as before, and the
    | layer fails closed. `enabled` is the master switch; `bypass_local` skips the
    | access checks under the `local` environment so development stays frictionless.
    | The policy lives in the headless package (InstallerAccessPolicy) and is
    | enforced by the web middleware. See docs/tools/security.md.
    |
    */

    'security' => [
        'enabled' => env('INSTALLER_SECURITY', true),
        'bypass_local' => env('INSTALLER_SECURITY_BYPASS_LOCAL', true),

        // Require HTTPS. Behind a host proxy, set the app's TrustProxies (or, as a
        // last resort, trust_forwarded_proto) so the scheme is detected correctly.
        'require_https' => env('INSTALLER_REQUIRE_HTTPS', false),
        'trust_forwarded_proto' => env('INSTALLER_TRUST_FORWARDED_PROTO', false),

        // Allowlists — comma-separated. IPs accept CIDR (IPv4/IPv6); hosts accept
        // wildcards. Empty = allow all. Loopback is auto-allowed outside production.
        'allowed_ips' => array_filter(explode(',', (string) env('INSTALLER_ALLOWED_IPS', ''))),
        'allowed_hosts' => array_filter(explode(',', (string) env('INSTALLER_ALLOWED_HOSTS', ''))),

        // Secret gate. Prefer `token_hash` (a bcrypt/argon hash) over the raw token.
        // Empty (both) = no gate. `single_use_token` invalidates it after install.
        'token' => env('INSTALLER_TOKEN'),
        'token_hash' => env('INSTALLER_TOKEN_HASH'),
        'token_header' => 'X-Installer-Token',
        'single_use_token' => env('INSTALLER_TOKEN_SINGLE_USE', false),

        // Accept a temporarySignedRoute signature as access (shareable expiring link).
        'signed_links' => env('INSTALLER_SIGNED_LINKS', false),

        // Availability window — 'Y-m-d H:i' (or null) in the given timezone
        // (null = config('app.timezone')). Open-ended on either side is allowed.
        'available_from' => env('INSTALLER_AVAILABLE_FROM'),
        'available_until' => env('INSTALLER_AVAILABLE_UNTIL'),
        'timezone' => env('INSTALLER_TIMEZONE'),
        'window_applies_to_cli' => env('INSTALLER_WINDOW_CLI', false),

        // Don't even register the web routes (404) once installed; emit security
        // response headers (no-store/DENY/nosniff/no-referrer/noindex).
        'disable_after_install' => env('INSTALLER_DISABLE_AFTER_INSTALL', true),
        'headers' => env('INSTALLER_SECURITY_HEADERS', true),

        'throttle' => [
            'max_attempts' => (int) env('INSTALLER_THROTTLE_MAX', 60),
            'decay_minutes' => (int) env('INSTALLER_THROTTLE_DECAY', 1),
            'gate_max_attempts' => (int) env('INSTALLER_GATE_THROTTLE_MAX', 5),
            'gate_lockout_minutes' => (int) env('INSTALLER_GATE_LOCKOUT', 15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hosting environment
    |--------------------------------------------------------------------------
    |
    | Tunes the installer for the host. `mode` (auto|shared|vps) drives preflight
    | guidance; `auto` detects restricted shared hosting (disabled proc_open, db/redis
    | session+cache). `session_store`/`cache_store` are forced for installer HTTP
    | requests so the wizard works before migrations create db-backed session/cache
    | tables (set null to leave the app's drivers untouched). `time_limit` is a
    | best-effort cap raised for long steps (0 = unlimited, null = leave as-is).
    |
    */

    'environment' => [
        'mode' => env('INSTALLER_ENV_MODE', 'auto'),
        'session_store' => env('INSTALLER_SESSION_STORE', 'file'),
        'cache_store' => env('INSTALLER_CACHE_STORE', 'file'),
        'time_limit' => env('INSTALLER_TIME_LIMIT', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment file paths
    |--------------------------------------------------------------------------
    |
    | Where the installer writes/reads the .env and reads the example template.
    | null falls back to base_path('.env') and base_path('.env.example').
    |
    */

    'env' => [
        'path' => null,
        'example' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI locales
    |--------------------------------------------------------------------------
    |
    | Locales offered on the welcome step. Keys are locale codes; values are
    | the human label shown in the picker. These come from the locale files
    | shipped with the package (no remote download).
    |
    */

    'locales' => [
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Server requirements
    |--------------------------------------------------------------------------
    |
    | Checked by the requirements step / `RequirementsChecker`. `extensions`
    | are hard requirements; `optional` do not block. `permissions` are paths
    | (relative to base_path) that must be writable.
    |
    */

    'requirements' => [
        'php' => env('INSTALLER_MIN_PHP', '8.4.1'),
        'extensions' => [
            'openssl',
            'pdo',
            'mbstring',
            'tokenizer',
            'json',
            'curl',
            'fileinfo',
            'ctype',
        ],
        'optional' => [
            'gd',
            'xml',
        ],
        'apache' => [
            'mod_rewrite',
        ],
        'permissions' => [
            '.env',
            'storage/framework/',
            'storage/logs/',
            'bootstrap/cache/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database / migrations
    |--------------------------------------------------------------------------
    |
    | `seeder` is an optional seeder class run after migrations (null = none).
    | `import` controls the optional SQL-dump import (delegated to
    | laranail/database-tools when present); disabled by default.
    |
    */

    'database' => [
        'seeder' => env('INSTALLER_SEEDER'),
        'import' => [
            'enabled' => false,
            'path' => env('INSTALLER_DB_IMPORT_PATH'),
            'connection' => env('INSTALLER_DB_IMPORT_CONNECTION'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk user import (ImportUsersStep — off by default)
    |--------------------------------------------------------------------------
    |
    | Source for the optional bulk user-import step: a CSV file `path` (header row
    | maps to columns) or an inline `rows` array of user maps. Security: a CSV with
    | plaintext passwords is sensitive — add it to `cleanup.files` to remove it.
    |
    */

    'users' => [
        'import' => [
            'path' => env('INSTALLER_USERS_IMPORT_PATH'),
            'rows' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User account
    |--------------------------------------------------------------------------
    |
    | The first user created by the user step. `model` is the Eloquent user
    | model; `fields` maps logical fields to your schema's column names so the
    | installer works on any app. `creator` may be an invokable/class that
    | fully overrides creation (receives the validated UserData). `role_driver`
    | selects how the role is assigned: null = auto-detect
    | (spatie|eloquent|null), or a driver key / FQCN.
    |
    */

    'user' => [
        'model' => env('INSTALLER_USER_MODEL', User::class),

        // 'single' = one `name` field/column; 'split' = first_name + last_name.
        'name_shape' => env('INSTALLER_NAME_SHAPE', 'single'),

        // Logical field => your schema's column name (works on any table).
        'fields' => [
            'name' => 'name',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'password' => 'password',
        ],
        'attributes' => [],
        'role_driver' => null,

        // The tool is generic — it does NOT assume "admin". Assign nothing by default;
        // set a role to assign one, and/or opt into making the first user an admin.
        'role' => env('INSTALLER_USER_ROLE'),
        'type' => env('INSTALLER_USER_TYPE'),
        'first_user_is_admin' => env('INSTALLER_FIRST_USER_ADMIN', false),
        'admin_role' => env('INSTALLER_ADMIN_ROLE', 'admin'),

        // Optional in-form role picker on the user step: [value => label]. Empty = no picker.
        'role_field' => [],
        'creator' => null,

        // Extra user-form fields (rendered + validated + persisted as attributes),
        // resolved by UserFormHooks. Either a flat list of field defs (all roles) or
        // a role-keyed map: ['admin' => [...], '*' => [...common]]. Each def:
        // ['name'=>, 'label'=>, 'type'=>'text', 'rules'=>[...], 'options'=>[...],
        //  'default'=>, 'sensitive'=>false, 'visible_when'=>['field'=>,'equals'=>]].
        'form_fields' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | License verification
    |--------------------------------------------------------------------------
    |
    | License verification is OFF by default (open-source / self-hosted). When
    | enabled, the license step delegates to laranail/license-verifier — set
    | its driver via `config('license-verifier.default')`. `skippable` lets the
    | user bypass the step.
    |
    */

    'license' => [
        'enabled' => env('INSTALLER_LICENSE', false),
        'skippable' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Step pipeline
    |--------------------------------------------------------------------------
    |
    | Declarative per-step configuration consumed by the StepRegistry. Each
    | entry may toggle `enabled` and override `priority`. Steps may also be
    | registered/reordered/replaced at runtime via StepRegistry. The generic
    | `choice` step is shipped but disabled — enable + supply `options` to
    | re-add a theme/preset-style selection without writing code.
    |
    */

    'steps' => [
        'welcome' => ['enabled' => true, 'priority' => 10],
        'requirements' => ['enabled' => true, 'priority' => 20],
        'environment' => ['enabled' => true, 'priority' => 30],
        'migrate' => ['enabled' => true, 'priority' => 40],
        'user' => ['enabled' => true, 'priority' => 50],
        'license' => ['enabled' => env('INSTALLER_LICENSE', false), 'priority' => 60],
        'final' => ['enabled' => true, 'priority' => 70],

        'choice' => [
            'enabled' => false,
            'priority' => 35,
            'label' => null,
            'options' => [],
        ],

        // Optional, off by default — registered so they're config-toggleable.
        'import-database' => ['enabled' => env('INSTALLER_DB_IMPORT', false), 'priority' => 45],
        'import-users' => ['enabled' => env('INSTALLER_USERS_IMPORT', false), 'priority' => 55],
    ],

    /*
    |--------------------------------------------------------------------------
    | Install lock / state
    |--------------------------------------------------------------------------
    |
    | Marker files (under storage_path) tracking install progress, and the
    | timeout (minutes) after which a stale in-progress install is abandoned.
    |
    */

    'lock' => [
        'installing' => 'installer.installing',
        'installed' => 'installer.installed',
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Channel used for structured installer logging. Secrets are always masked.
    |
    */

    'logging' => [
        'channel' => env('INSTALLER_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wizard
    |--------------------------------------------------------------------------
    |
    | Cross-step input persistence powers resume and back/forward re-hydration.
    | By default only non-sensitive fields are persisted (sensitive fields are
    | re-entered each visit). Set `persist_secrets` to true to also persist
    | sensitive fields, encrypted at rest via Laravel's encrypter.
    |
    */

    'wizard' => [
        'persist_input' => env('INSTALLER_PERSIST_INPUT', true),
        'persist_secrets' => env('INSTALLER_PERSIST_SECRETS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-install cleanup
    |--------------------------------------------------------------------------
    |
    | Files (relative to base_path) deleted by the final step once installation
    | completes — e.g. a bundled `database.sql` seed dump. Empty by default so
    | nothing is removed unless you opt in.
    |
    */

    'cleanup' => [
        'files' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Off by default. When enabled, install completion/failure is sent to the listed
    | recipients (on-demand notifications — no notifiable required). Channels are
    | pluggable: `channels` lists the notification channels (default `mail`); the
    | `mail` channel routes to `mail.to`, other channels to `routes.<channel>`.
    | `security` is a separate, independently-enabled stream for access-denied alerts.
    |
    */

    'notifications' => [
        'enabled' => env('INSTALLER_NOTIFICATIONS', false),
        'channels' => array_filter(explode(',', (string) env('INSTALLER_NOTIFY_CHANNELS', 'mail'))),
        'mail' => [
            'to' => array_filter(explode(',', (string) env('INSTALLER_NOTIFY_EMAILS', ''))),
        ],
        // Per-channel routes for non-mail channels, e.g. ['slack' => 'https://hooks.slack…'].
        'routes' => [],
        'security' => [
            'enabled' => env('INSTALLER_SECURITY_ALERTS', false),
            'to' => array_filter(explode(',', (string) env('INSTALLER_SECURITY_ALERT_EMAILS', ''))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Products (optional, multi-product installs)
    |--------------------------------------------------------------------------
    |
    | Declarative product pipelines run via `InstallerEngine::forProduct('<slug>')`
    | (or the CLI `--product=<slug>` / `--all-products`). Each product gets its own
    | isolated install state/progress/resume AND its own step pipeline. Empty = a
    | single default-pipeline install (unchanged behaviour).
    |
    | Per product (all keys optional):
    |   'type'           => 'app' | 'module' | 'plugin'  — preset default steps
    |   'steps'          => ['requirements', 'migrate', 'license', 'final']  — ordered;
    |                       overrides the preset; the order here is the run order.
    |                       Listed steps run even if globally disabled (e.g. license).
    |   'priorities'     => ['migrate' => 25]            — explicit priority overrides
    |   'config'         => ['database' => ['seeder' => …]]  — per-product config,
    |                       read by steps via $context->productConfig('database.seeder')
    |   'config_overlay' => false                        — when true, merge 'config'
    |                       into global installer.* during this product's run
    |   'label'          => 'My Add-on'
    |
    | Example:
    |   'addon-x' => [
    |       'type'  => 'module',
    |       'steps' => ['requirements', 'migrate', 'license', 'final'],
    |       'config' => ['database' => ['seeder' => \Database\Seeders\AddonXSeeder::class]],
    |   ],
    |
    */

    'products' => [],

];
