# laravel-dblens

A phpMyAdmin-style database browser & manager for Laravel — built with Blade, Tailwind and Alpine.js. Browse, search, edit rows, alter schema, import/export, and run SQL — all from a clean dashboard mounted in your app.

> ⚠️ DbLens is a **powerful tool**. By default it is enabled only in the `local` environment. Read the [Security](#security) section before turning it on in production.

---

## Features

### Browsing
- 🗂️ List databases / tables of any configured Laravel DB connection
- 🎛️ Dashboard with quick-action cards (SQL · ER · DB Objects · About) + per-DB stats (tables / rows / size / largest)
- 📊 Table info card (engine, collation, size, rows, auto-increment, created/updated, comment)
- 🧱 Structure view: columns, indexes, outgoing FKs, **incoming** FKs (who references this table)
- 🔍 **Per-table search** plus optional **per-column inline filters** in the header row
- 🌐 **Global cross-table search** — finds a term in every text column of every table
- 🔗 **Clickable foreign keys** — clicking an FK cell jumps to the referenced row
- 👁️ **Click-to-open row preview** — full row data with column/value search and "not-null only" toggle, no page navigation
- 🌳 **Tree view** for self-referential tables (parent_id → id) — expandable hierarchy with per-node child search, jump-to-row, browse-children buttons
- 🪶 **Soft-delete aware** — rows with non-null `deleted_at` highlighted in red across browse and tree; one-click "Not deleted" filter; soft-delete option in the delete confirm; ↩ restore button
- 📦 **JSON columns** detected at type and value level — opened in a full modal editor with pretty-print, minify, live validation, auto-minify on save
- ↕️ Column sort, server-side pagination, configurable per-page
- ⚡ **Approximate row count** on huge tables — uses engine stats instead of `COUNT(*)` (threshold configurable)
- 🙈 Mask sensitive columns (`password`, `remember_token`, …) by name

### Row CRUD
- ✏️ Insert / edit / delete rows (icon-only action column)
- ⚡ **Inline cell editor** — double-click any cell; auto-picks the right input (FK lookup, enum, bool, JSON modal, date, number, text). `∅` button to set NULL on nullable columns
- 🧠 **Smart cell content viewer** on the row page — collapsible JSON tree, image preview (URL/base64), Markdown, XML, URL detection
- 🔁 **Related rows panel** on the row page — for every incoming FK, fetches the actual related rows (configurable preview limit) with one-click navigation
- 📋 Auto-generated forms from column metadata
- 🗑️ Bulk delete from the browse view (checkboxes + select-all)
- 🛡️ Read-only mode flag disables every write path
- 💥 Destructive ops require a confirmation flag + JS confirm dialog

### Schema editing
- ➕ Add / modify / rename / drop columns (inline forms on the structure page)
- 🔑 Add / drop indexes (single or composite, unique or not)
- 🔗 Add / drop foreign keys (with `ON UPDATE` / `ON DELETE` actions)
- 🏗️ Create tables (wizard with auto-increment + composite primary keys)
- 🪪 Rename table / Truncate / Drop table

### SQL editor
- ⚡ Run arbitrary SQL with timing + row-count display
- 🧭 **EXPLAIN** button — driver-aware query plan (`EXPLAIN` / `EXPLAIN QUERY PLAN`) without executing the query
- 🔒 Read-only by default; flip a config flag to enable writes
- ✂️ Result-set truncation cap so a `SELECT *` on a huge table can't crash the page

### Database objects
- 🧩 Browse views, stored routines (procedures/functions), triggers, and events
- 📜 View full DDL definition for each object
- 🗑️ Drop any object (respects `read_only`)

### ER diagram
- 🗺️ Interactive zoom/pan/drag schema map with FK arrows
- 🎯 Click any arrow to see `from.col → to.col` and the FK constraint name in a floating popup positioned over the line
- ⭐ Click a star on any table to "activate" it; `related only` mode hides everything except active + its FK neighbors, auto-arranged in a ring and centered in view
- 🎛️ `active only` toggle scopes arrows to the active table; `arrows` toggle for the global view
- 💾 **Saved views** — persist a fully laid-out diagram (positions + zoom + toggles + active table) as JSON; reload in one click
- 📑 Side panel listing all outgoing/incoming FKs for the active table — click any to jump
- 🔍 Search with Enter / Shift+Enter to jump between matches

### Multi-tenancy (stancl/tenancy)
- 🏢 Auto-detects an initialized tenant via [stancl/tenancy](https://tenancy-docs.com) and shows a violet pill in the topbar (`🏢 acme #t-42 · acme.app.test`)
- 🗂️ Browses the dynamic `tenant` connection naturally — no extra config when DbLens routes are registered inside the tenant route group
- 🧠 Silently no-ops when the package isn't installed

**Setup:** register DbLens routes inside the tenant context. In `routes/tenant.php`:

```php
Route::middleware([
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
])->group(function () {
    // DbLens auto-registers under /dblens for any group it's mounted in.
    // No code needed — the package's service provider already loaded its
    // routes against the current route() context.
});
```

Set `DBLENS_DEFAULT_CONNECTION=tenant` (or use the `connections.default` config) so DbLens opens the tenant DB by default.

### Activity log integration
- 📜 Auto-records every write through **spatie/laravel-activitylog** (optional, auto-detected when installed)
- 🧩 Logged events: `row_created/updated/deleted/bulk_deleted`, `cell_updated`, `schema_*` (column/index/FK/table DDL), `schema_object_dropped` (views/routines/triggers/events), `sql_executed`, `import_sql/csv`
- 🧱 Captures pre-image (`old`) + post-image (`new`) + computed `diff` on row updates
- 🪶 Redacts configured sensitive columns; truncates long values
- 🔕 Granular per-event toggles + wildcard prefix matching (`row_*`, `schema_*`, etc.)
- 🌐 Zero-config: enabled automatically when the Spatie package is installed AND its `activity_log` table exists; silently no-ops otherwise

### Export & Import
- ⬇️ Per-table export → `SQL` (DROP + CREATE + batched INSERTs), `CSV`, or `JSON`
- ⬇️ Full-database SQL dump (streamed; works on huge tables — uses a PDO cursor)
- ⬆️ Import SQL dump (transaction-wrapped; respects strings, backticks, line + block comments)
- ⬆️ Import CSV into an existing table (header-based column matching or positional)

### Artisan commands
- `php artisan dblens:install` — publish config, verify env, list DB connections (add `--views` for full template control)
- `php artisan dblens:publish-config` — publish only the config file to `config/dblens.php` (add `--force` to overwrite an existing one)
- `php artisan dblens:make-migration {table}` — generate a Laravel migration from an existing table (columns + indexes + FKs)

### Security
- 🚫 Disabled in production by default (`enable_production = false`)
- 🛂 Laravel **Gate** authorization (`viewDbLens`)
- 🪣 Custom **file-based throttle** (doesn't depend on your cache driver)
- 🔐 `read_only` flag that disables every mutation, regardless of route
- ✅ Explicit confirmation flag required for destructive operations
- 🎯 Allowlist of permitted DB connections (`config('dblens.connections.allowed')`)
- 🔍 Identifier quoting on every dynamic name — no string concatenation of unsafe input
- 🩺 **Friendly connection-error page** — misconfigured drivers (e.g. `pgsql` on a MySQL port) render a clean 503 instead of a stack trace

### Supported drivers
- MySQL / MariaDB
- PostgreSQL (limited DDL export — data only in full-DB dump)
- SQLite (limited schema mutations — many `ALTER` ops require table rebuild)

---

## Installation

### Requirements

- PHP `^8.1`
- Laravel `10.x` / `11.x` / `12.x` / `13.x`
- One of: MySQL, MariaDB, PostgreSQL, SQLite

### 1. Install the package

```bash
composer require mahmoud-mhamed/laravel-dblens
```

The service provider is auto-registered via Laravel's package discovery — no manual `config/app.php` edits required.

### 2. Run the installer

```bash
php artisan dblens:install
```

This single command publishes both the config and the views (equivalent to running the two `vendor:publish` commands below).

To overwrite already-published files:

```bash
php artisan dblens:install --force
```

### 3. Visit DbLens

```
https://your-app.test/dblens
```

You'll be redirected to the default connection's database page. In `local` it works out of the box; for other environments, see [Authorization](#authorization).

---

## Publishing assets

`dblens:install` is a wrapper around Laravel's `vendor:publish`. You can also publish individual tags:

### Publish the config only

```bash
php artisan vendor:publish --tag=dblens-config
```

Creates `config/dblens.php`. Edit it to change route prefix, gate, throttle, allowed connections, masked columns, etc. See [Configuration](#configuration).

### Publish the views only

```bash
php artisan vendor:publish --tag=dblens-views
```

Creates `resources/views/vendor/dblens/`. Laravel will load views from this folder **instead of** the package's bundled views, so you can customize the look freely.

### Re-publish (overwrite local copies)

```bash
php artisan vendor:publish --tag=dblens-config --force
php artisan vendor:publish --tag=dblens-views  --force
```

> ⚠️ `--force` overwrites your local edits in `config/dblens.php` and `resources/views/vendor/dblens/`. Commit before running it.

### Stop using the published views

Delete the `resources/views/vendor/dblens/` folder — Laravel will fall back to the package's bundled views automatically.

### Environment variables (`.env`)

The most common knobs are exposed as env vars so you can toggle per-environment without editing the config:

```dotenv
DBLENS_ENABLE_LOCAL=true
DBLENS_ENABLE_PRODUCTION=false
DBLENS_READONLY=false
DBLENS_PATH=dblens
# DBLENS_DOMAIN=admin.your-app.test
DBLENS_DEFAULT_CONNECTION=mysql
DBLENS_SQL_WRITES=false
# DBLENS_PASSWORD='$2y$12$...'   # bcrypt hash recommended

# Activity log (spatie/laravel-activitylog) — defaults to "auto"
# DBLENS_LOG_ACTIVITY=auto         # true | false | auto
# DBLENS_LOG_CONNECTION=mysql      # null → spatie default
```

After changing any of these, clear the config cache if you cache config in production:

```bash
php artisan config:clear
# or
php artisan config:cache
```

### Uninstalling

```bash
composer remove mahmoud-mhamed/laravel-dblens
rm  config/dblens.php
rm -rf resources/views/vendor/dblens
rm -rf storage/framework/cache/dblens   # throttle state
```

---

## Configuration

Open `config/dblens.php`. The defaults are sane for local dev; the keys you most likely want to tweak:

```php
return [
    // Environment switches
    'enable_local' => true,
    'enable_production' => false,           // turn on with caution
    'read_only' => env('DBLENS_READONLY', false),

    // URL + access
    'viewer' => [
        'enabled' => true,
        'path' => env('DBLENS_PATH', 'dblens'),
        'domain' => env('DBLENS_DOMAIN'),
        'middleware' => ['web', 'auth'],        // require a logged-in Laravel user
        'password' => env('DBLENS_PASSWORD'),   // extra dashboard password (optional)
    ],

    // Authorization: define this gate in a service provider
    'gate' => 'viewDbLens',

    // Throttle (file-based, independent of your cache driver)
    'throttle' => ['enabled' => true, 'attempts' => 120, 'minutes' => 1],

    // Connections
    'connections' => [
        'allowed' => [],                    // empty = allow all from config/database.php
        'default' => env('DBLENS_DEFAULT_CONNECTION', env('DB_CONNECTION', 'mysql')),
    ],

    // SQL editor
    'sql_editor' => [
        'enabled' => true,
        'allow_writes' => env('DBLENS_SQL_WRITES', false),
        'max_rows' => 1000,
        'timeout_seconds' => 30,
    ],

    // Browse
    'browse' => [
        'per_page' => 30,
        'per_page_options' => [10, 30, 50, 100, 200],
        'truncate_cell' => 120,
    ],

    // Mask values for these column names (case-insensitive)
    'masked_columns' => [
        'password', 'remember_token', 'api_token', 'secret',
    ],

    // Require a `confirm=1` request flag for destructive operations
    'confirm_destructive' => true,
];
```

### Environment variables

| Var | Default | Description |
|---|---|---|
| `DBLENS_ENABLE_LOCAL` | `true` | Enable in local |
| `DBLENS_ENABLE_PRODUCTION` | `false` | Enable in production |
| `DBLENS_READONLY` | `false` | Global write lock |
| `DBLENS_PATH` | `dblens` | URL prefix |
| `DBLENS_DOMAIN` | — | Mount on a subdomain |
| `DBLENS_DEFAULT_CONNECTION` | `DB_CONNECTION` | Pre-selected connection |
| `DBLENS_SQL_WRITES` | `false` | Allow INSERT/UPDATE/DELETE/ALTER in the SQL editor |
| `DBLENS_PASSWORD` | — | Dashboard password (bcrypt hash recommended). When unset, the password gate is skipped. |
| `DBLENS_LOG_ACTIVITY` | `auto` | Activity log integration: `true` / `false` / `auto` (auto-enable when spatie/laravel-activitylog is installed and its table exists) |
| `DBLENS_LOG_CONNECTION` | — | DB connection used by activity log writes (defaults to spatie's `activitylog.database_connection`) |
| `DBLENS_TENANCY` | `auto` | Multi-tenancy integration: `true` / `false` / `auto` (auto-detect stancl/tenancy) |

---

## Authorization

DbLens has **three** independent gates you can combine:

1. **Route middleware** (`config('dblens.viewer.middleware')`) — runs first. Defaults to `['web', 'auth']`, so anonymous visitors are bounced to your app's login page. Drop `'auth'` for an open dashboard, or replace it with `'auth:admin'` / a custom middleware.
2. **Dashboard password** (`config('dblens.viewer.password')`) — when set, visitors must enter this password on `/dblens/login` before they can use the dashboard. Stored as a session flag.
3. **Laravel Gate** (`config('dblens.gate')`) — optional final check. When set, the gate ability is checked on every request.

### 1. Middleware

The default already gates the dashboard behind Laravel's regular auth — no changes needed if you're using the standard `auth` middleware:

```php
// config/dblens.php  (default)
'viewer' => [
    'middleware' => ['web', 'auth'],
],
```

Customize per your stack:

```php
'middleware' => ['web', 'auth:admin'],              // a guard called 'admin'
'middleware' => ['web', 'auth', 'can:access-admin'], // chain a permission
'middleware' => ['web'],                             // open dashboard (rely on password / Gate below)
```

### 2. Dashboard password (recommended for production)

Generate a bcrypt hash:

```bash
php artisan tinker
>>> echo Hash::make('your-secret-password');
```

Add to `.env`:

```dotenv
DBLENS_PASSWORD='$2y$12$...'   # paste the hash
```

Plain strings also work (compared in constant time) — but a hash is safer because it never appears in logs or stack traces in plaintext form. Set the var to empty to disable the password gate.

When the password is set, the middleware redirects every unauthenticated request to `/dblens/login`. After a correct submission, a session flag (`dblens_authenticated`) is set and the user can use the dashboard until they log out or the session expires.

A **Logout** button automatically appears in the topbar when the password is configured.

### 3. Laravel Gate (advanced)

Override the default gate in your `AuthServiceProvider` (or any service provider's `boot()`):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewDbLens', function ($user) {
    return $user?->is_admin === true;
});
```

The package's default `viewDbLens` gate only allows access in the `local` environment. Setting `config('dblens.gate')` to `null` disables the gate check entirely (you can rely on middleware + password instead).

### Putting it together — typical setups

| Environment | middleware | password | gate |
|---|---|---|---|
| Local dev (just me) | `['web']` | `null` | `'viewDbLens'` (auto-allows local) |
| Staging (shared) | `['web', 'auth']` *(default)* | bcrypt hash | `null` |
| Production (gated) | `['web', 'auth']` *(default)* | bcrypt hash | `'viewDbLens'` w/ `is_admin` check |
| Public internet | **do not run** | — | — |

---

## URL map

All routes are mounted under `config('dblens.viewer.path')` (default `/dblens`) and are named under the `dblens.` prefix.

| Method | URI | Name |
|---|---|---|
| GET / POST | `/login` | `dblens.login` · `dblens.login.submit` |
| POST | `/logout` | `dblens.logout` |
| GET | `/` | `dblens.dashboard` |
| GET | `/{conn}` | `dblens.database.show` |
| GET | `/{conn}/search?q=…` | `dblens.search` |
| GET | `/{conn}/sql` · POST `/{conn}/sql` | `dblens.sql.show` · `dblens.sql.run` |
| GET | `/{conn}/export` | `dblens.database.export` |
| GET | `/{conn}/import` · POST `/{conn}/import` | `dblens.database.import.form` · `dblens.database.import` |
| GET | `/{conn}/create-table` · POST | `dblens.table.create.form` · `dblens.table.create` |
| GET | `/{conn}/t/{table}` | `dblens.table.browse` |
| GET | `/{conn}/t/{table}/structure` | `dblens.table.structure` |
| GET | `/{conn}/t/{table}/info` | `dblens.table.info` |
| GET | `/{conn}/t/{table}/export?format=sql\|csv\|json` | `dblens.table.export` |
| GET | `/{conn}/t/{table}/import` · POST `/.../import-csv` | `dblens.table.import.form` · `dblens.table.import.csv` |
| POST/PUT/DELETE | `/{conn}/t/{table}/columns/{column?}` | `dblens.column.add/modify/rename/drop` |
| POST/DELETE | `/{conn}/t/{table}/indexes/{index?}` | `dblens.index.add/drop` |
| POST | `/{conn}/t/{table}/foreign-keys` | `dblens.fk.add` |
| DELETE | `/{conn}/t/{table}/fk/{fk}` | `dblens.table.fk.drop` |
| POST | `/{conn}/t/{table}/truncate` | `dblens.table.truncate` |
| POST | `/{conn}/t/{table}/rename` | `dblens.table.rename` |
| DELETE | `/{conn}/t/{table}` | `dblens.table.drop` |
| GET | `/{conn}/t/{table}/r/{rowKey}` | `dblens.row.show` |
| GET/PUT | `/{conn}/t/{table}/r/{rowKey}/edit` | `dblens.row.edit/update` |
| DELETE | `/{conn}/t/{table}/r/{rowKey}` | `dblens.row.destroy` |
| GET/POST | `/{conn}/t/{table}/create` | `dblens.row.create/store` |
| POST | `/{conn}/t/{table}/bulk-delete` | `dblens.row.bulk-destroy` |

Row keys are URL-encoded JSON for composite primary keys (e.g. `{"a":1,"b":2}`) or a plain value for single-column PKs.

---

## Architecture

```
src/
├── DbLensServiceProvider.php       routes, middleware, publish, services
├── Services/
│   ├── ConnectionManager.php       allowlist + Laravel connection factory
│   ├── SchemaInspector.php         tables/columns/indexes/FKs/info
│   ├── QueryRunner.php             browse, findRow, globalSearch, runRaw
│   ├── RowEditor.php               insert/update/delete + bulkDelete
│   ├── TableEditor.php             column/index/FK/table mutations
│   ├── Exporter.php                streamed SQL/CSV/JSON export
│   └── Importer.php                SQL + CSV import (transactional)
├── Support/Drivers/
│   ├── DriverInterface.php
│   ├── MySqlDriver.php
│   ├── PgsqlDriver.php
│   └── SqliteDriver.php
├── Http/
│   ├── Controllers/                Dashboard, Database, Table, Row, Schema, Sql, Export, Import
│   └── Middleware/                 AuthorizeDbLens, DbLensThrottle (file-based)
└── Console/Commands/
    └── DbLensInstallCommand.php
```

Driver implementations isolate vendor-specific SQL. Adding a new driver = implementing `DriverInterface`.

---

## Caveats per driver

**MySQL / MariaDB:** Full feature support.

**PostgreSQL:**
- Schema mutations supported, but `MODIFY COLUMN` is split into 3 separate `ALTER`s (type, null, default).
- Full-database SQL dump exports **data only** — re-creating the DDL is out of scope; use `pg_dump` for full schema dumps.

**SQLite:**
- `MODIFY COLUMN`, `DROP FOREIGN KEY`, `ADD FOREIGN KEY` (via `ALTER`) — not supported by SQLite itself; DbLens throws a clear error rather than silently rebuilding the table.
- `TRUNCATE` is emulated as `DELETE FROM`.

---

## Roadmap

- Phase 5 (planned): SQL history + saved queries + ER diagram + slow-log viewer

---

## License

MIT © [Mahmoud Mhamed](https://github.com/mahmoud-mhamed)
