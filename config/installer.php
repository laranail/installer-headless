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
    | Off by default. When enabled, install completion/failure is emailed to the
    | listed recipients (on-demand mail notifications — no notifiable required).
    |
    */

    'notifications' => [
        'enabled' => env('INSTALLER_NOTIFICATIONS', false),
        'mail' => [
            'to' => array_filter(explode(',', (string) env('INSTALLER_NOTIFY_EMAILS', ''))),
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
