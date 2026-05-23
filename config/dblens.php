<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment Switches
    |--------------------------------------------------------------------------
    |
    | DbLens is a powerful tool — it can read, modify and drop data. By
    | default it is enabled in local and disabled in production.
    |
    */
    'enable_local' => env('DBLENS_ENABLE_LOCAL', true),
    'enable_production' => env('DBLENS_ENABLE_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Read-only Mode
    |--------------------------------------------------------------------------
    |
    | When true the package will refuse any INSERT/UPDATE/DELETE/ALTER/DROP
    | operation. Useful for production / shared environments.
    |
    */
    'read_only' => env('DBLENS_READONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Per-action permissions
    |--------------------------------------------------------------------------
    |
    | Even when read_only is false, the following flags can disable specific
    | destructive / heavy actions. Each can be toggled independently via env.
    |
    | allow_truncate   — TRUNCATE TABLE
    | allow_drop_table — DROP TABLE
    | allow_export     — download SQL / CSV / JSON dumps
    | allow_import     — upload SQL / CSV files
    |
    */
    'allow_truncate' => env('DBLENS_ALLOW_TRUNCATE', true),
    'allow_drop_table' => env('DBLENS_ALLOW_DROP_TABLE', true),
    'allow_export' => env('DBLENS_ALLOW_EXPORT', true),
    'allow_import' => env('DBLENS_ALLOW_IMPORT', true),

    /*
    |--------------------------------------------------------------------------
    | Web Viewer
    |--------------------------------------------------------------------------
    |
    | middleware:
    |   Middleware applied to ALL dashboard routes. The default ['web', 'auth']
    |   gates the dashboard behind the regular Laravel login — anonymous
    |   visitors are redirected to your app's login page. Replace 'auth' with
    |   'auth:admin' or your own middleware (Spatie permission, custom gate,
    |   etc.) as needed. Use ['web'] alone to open the dashboard to anyone
    |   who passes the password / Gate checks below.
    |
    | password:
    |   Optional, dashboard-specific password. Independent of (and additive
    |   to) the `middleware` chain above. When set, visitors must enter this
    |   password on /dblens/login before they can use the dashboard.
    |   - Store a bcrypt hash for safety:
    |       DBLENS_PASSWORD='$2y$12$...'
    |   - Plain string also works (back-compat, compared in constant time).
    |   - Set to null to disable the password screen.
    |
    */
    'viewer' => [
        'enabled' => true,
        'path' => env('DBLENS_PATH', 'dblens'),
        'domain' => env('DBLENS_DOMAIN'),
        'middleware' => ['web', 'auth'],
        'password' => env('DBLENS_PASSWORD', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate (optional)
    |--------------------------------------------------------------------------
    |
    | An additional Laravel Gate ability checked after the password gate.
    | When the gate returns false the request is rejected with 403. Set to
    | null to disable. The default 'viewDbLens' is registered by the
    | package to allow access only in the `local` environment — override
    | by defining your own `Gate::define('viewDbLens', …)`.
    |
    */
    'gate' => 'viewDbLens',

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    */
    'throttle' => [
        'enabled' => true,
        'attempts' => 120,
        'minutes' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | The list of Laravel DB connections DbLens is allowed to use. Leave
    | `allowed` empty to allow ALL connections defined in config/database.php
    |
    */
    'connections' => [
        'allowed' => [],
        'default' => env('DBLENS_DEFAULT_CONNECTION', env('DB_CONNECTION', 'mysql')),
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Editor
    |--------------------------------------------------------------------------
    */
    'sql_editor' => [
        'enabled' => true,
        'allow_writes' => env('DBLENS_SQL_WRITES', false),
        'max_rows' => 1000,
        'timeout_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Browse
    |--------------------------------------------------------------------------
    */
    'row' => [
        // How many child rows to preview per incoming FK on the row.show page.
        // Set to 0 to disable previews (only counts will be shown).
        'related_preview_limit' => 5,
    ],

    'browse' => [
        'per_page' => 30,
        'per_page_options' => [10, 30, 50, 100, 200],
        'truncate_cell' => 120,
        // When a table has at least this many rows (per the engine's stats) and
        // there's no WHERE clause, use the cheap approximate count instead of
        // running COUNT(*). Set to 0 to always use exact COUNT(*).
        'approx_count_threshold' => 100000,
    ],

    /*
    |--------------------------------------------------------------------------
    | ER diagram
    |--------------------------------------------------------------------------
    | Where saved ER views are persisted as JSON.
    */
    'er' => [
        'storage_disk' => null, // null → filesystems.default
        'storage_path' => 'dblens',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity log integration (Spatie laravel-activitylog)
    |--------------------------------------------------------------------------
    | DbLens can record every write operation (insert / update / delete /
    | truncate / DDL / raw SQL) through spatie/laravel-activitylog if it's
    | installed in the host application.
    |
    | `enabled` accepts:
    |   - true  → always enabled (will silently no-op if package missing)
    |   - false → disabled
    |   - 'auto' (default) → auto-detect: enabled when the spatie package is
    |     installed AND its `activity_log` table exists on the default DB
    |     connection. This is the recommended setting.
    */
    'activity_log' => [
        'enabled' => env('DBLENS_LOG_ACTIVITY', 'auto'),
        'log_name' => 'dblens',
        'connection' => env('DBLENS_LOG_CONNECTION'), // null → spatie default
        // Capture the row's pre-image (SELECT before UPDATE/DELETE). Adds I/O.
        'capture_old_values' => true,
        // Persist the raw SQL of write queries run through the SQL editor.
        // Off by default — SQL may contain sensitive literal values.
        'capture_sql' => false,
        // Truncate long string values stored in properties.
        'max_value_length' => 5000,
        // Column names whose values are replaced with "•••" before logging.
        'redact_columns' => ['password', 'remember_token', 'api_token', 'secret'],
        // Per-event toggles. Wildcards supported via prefix match.
        'events' => [
            'row_*' => true,
            'schema_*' => true,
            'sql_executed' => false,
            'import_*' => false,
            'export_*' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent Model Cast Detection
    |--------------------------------------------------------------------------
    |
    | DbLens can scan your Eloquent models to detect PHP enum casts and
    | render dropdowns of enum cases in the row editor / inline cell editor.
    |
    | models_path  — folder scanned for Model classes. Set to null to disable.
    | models_namespace — base namespace for those classes.
    | casts        — manual override / addition. Format:
    |                ['table_name' => ['column_name' => App\Enums\Status::class]]
    |
    */
    'models_path' => app_path('Models'),
    'models_namespace' => 'App\\Models',
    'casts' => [
        // 'orders' => ['status' => \App\Enums\OrderStatus::class],
    ],

    /*
    |--------------------------------------------------------------------------
    | Masked Columns
    |--------------------------------------------------------------------------
    |
    | Column names whose values will be masked in browse / row views. Match
    | is case-insensitive against the column name.
    |
    */
    'masked_columns' => [
        'password',
        'remember_token',
        'api_token',
        'secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dangerous Operations
    |--------------------------------------------------------------------------
    |
    | Even with read_only=false, these operations require an explicit
    | confirmation flag in the request.
    |
    */
    'confirm_destructive' => true,
];
