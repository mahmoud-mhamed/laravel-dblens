@extends('dblens::layout')

@section('content')
<div class="space-y-6">
    {{-- Hero --}}
    <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-sky-50 to-white p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">🔍 DbLens</h1>
                <p class="text-slate-600 text-sm mt-2 max-w-2xl leading-relaxed">
                    A phpMyAdmin-style browser for your Laravel database — built as a small Blade + Alpine package.
                    Browse tables, edit rows inline, run SQL, manage schema, view the ER diagram, and explore
                    views/triggers/procedures, all without leaving your app.
                </p>
                <div class="flex flex-wrap gap-2 mt-3 text-xs">
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">Laravel 10 → 13</span>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">PHP 8.1+</span>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">MySQL · PostgreSQL · SQLite</span>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">Tailwind · Alpine.js</span>
                </div>
            </div>
            <div class="text-right text-xs text-slate-500 mono shrink-0">
                <div>v{{ $version ?? 'dev' }}</div>
                <div>PHP {{ PHP_VERSION }}</div>
                <div>Laravel {{ app()->version() }}</div>
            </div>
        </div>
    </div>

    {{-- Quick environment --}}
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold">Environment</div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-slate-100 text-xs">
            @foreach ([
                'Active connection' => $connection ?? '—',
                'Database' => $database ?? '—',
                'Tables' => isset($tables) ? count($tables) : '—',
                'Read-only' => config('dblens.read_only') ? 'YES' : 'no',
                'Allow writes (SQL editor)' => config('dblens.sql_editor.allow_writes') ? 'YES' : 'no',
                'Local enabled' => config('dblens.enable_local') ? 'YES' : 'no',
                'Production enabled' => config('dblens.enable_production') ? 'YES' : 'no',
                'Throttle' => config('dblens.throttle.enabled', true) ? 'on' : 'off',
                'Activity log' => app(\MahmoudMhamed\DbLens\Services\ActivityLogger::class)->isEnabled() ? 'active' : (class_exists(\Spatie\Activitylog\Models\Activity::class) ? 'available (off)' : 'not installed'),
                'Tenancy' => app(\MahmoudMhamed\DbLens\Services\TenancyManager::class)->isInitialized()
                    ? 'tenant '.(app(\MahmoudMhamed\DbLens\Services\TenancyManager::class)->current()['name'] ?? app(\MahmoudMhamed\DbLens\Services\TenancyManager::class)->current()['id'])
                    : (app(\MahmoudMhamed\DbLens\Services\TenancyManager::class)->isAvailable() ? 'available (central)' : 'not installed'),
            ] as $k => $v)
                <div class="bg-white px-4 py-3">
                    <div class="text-[10px] uppercase text-slate-400">{{ $k }}</div>
                    <div class="mono font-semibold text-slate-700 truncate">{{ $v }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Features --}}
    <div>
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Features</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ([
                ['🗂', 'Table browser', 'Paginated rows with per-column inline filters, row-preview popover (search + not-null toggle), sort, search, bulk delete.'],
                ['✏️', 'Inline cell editor', 'Double-click any cell to edit in place — text, numbers, dates, enums, FK lookups, JSON modal with format/minify/validate, NULL button.'],
                ['🪶', 'Soft-delete aware', 'Rows with `deleted_at` highlighted red across browse + tree; one-click "Not deleted" filter, soft-delete option in confirm dialog, ↩ restore button.'],
                ['🌳', 'Tree view', 'Hierarchical view for self-referential tables with per-node child search, jump-to-row, browse-children buttons, expand/collapse all.'],
                ['🧱', 'Schema editor', 'Add / rename / drop columns and indexes, manage foreign keys, drop or truncate tables.'],
                ['⚡', 'SQL editor', 'Run any query with row-limit safety. EXPLAIN button for driver-aware query plans.'],
                ['🔗', 'Smart cell viewer', 'JSON tree, image preview (URL/base64), markdown, XML, URL detection — all rendered on the row page.'],
                ['🔁', 'Related rows', 'Row page shows actual related rows from each incoming FK with one-click navigation, not just counts.'],
                ['🗺️', 'ER diagram', 'Interactive zoom/pan/drag map; click arrows for FK details; star a table to focus, related-only mode auto-arranges neighbors.'],
                ['📁', 'Saved ER views', 'Persist a fully laid-out diagram (positions, zoom, toggles, active table) as JSON and reload in one click.'],
                ['🧩', 'DB objects', 'Browse views, stored routines, triggers, events. Drop them from the UI.'],
                ['📜', 'Activity log', 'Auto-records every write through spatie/laravel-activitylog when installed — row CRUD, DDL, SQL, imports. Captures old/new/diff with redaction.'],
                ['🏢', 'Multi-tenancy', 'Auto-detects stancl/tenancy. Shows current tenant in the topbar and browses its DB transparently when routes run in tenant context.'],
                ['📥', 'Import / export', 'Per-table CSV / JSON / SQL export and SQL/CSV import.'],
                ['🛡', 'Permissions', 'Granular gates per action (browse, edit, ddl, drop, truncate, export, import).'],
                ['🩺', 'Friendly errors', 'Misconfigured drivers (e.g. wrong port/driver) render a clean 503 page instead of a stack trace.'],
                ['🎛', 'Drivers', 'First-class MySQL, PostgreSQL, and SQLite drivers with a shared interface.'],
                ['🧪', 'Tested', 'Pest + Orchestra Testbench; SQLite in-memory for unit and feature coverage.'],
            ] as $feat)
                <div class="bg-white border border-slate-200 rounded p-4 hover:border-sky-300 hover:shadow transition">
                    <div class="text-2xl">{{ $feat[0] }}</div>
                    <div class="font-semibold text-sm mt-1">{{ $feat[1] }}</div>
                    <div class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $feat[2] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Commands --}}
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold">Artisan commands</div>
        <div class="divide-y text-sm">
            <div class="px-4 py-3">
                <code class="text-sky-700 mono">php artisan dblens:install</code>
                <div class="text-xs text-slate-500 mt-1">Publish config, verify env, list DB connections.</div>
            </div>
            <div class="px-4 py-3">
                <code class="text-sky-700 mono">php artisan dblens:install --views</code>
                <div class="text-xs text-slate-500 mt-1">Same, also publishes Blade views for full customization.</div>
            </div>
            <div class="px-4 py-3">
                <code class="text-sky-700 mono">php artisan dblens:make-migration {table}</code>
                <div class="text-xs text-slate-500 mt-1">Generate a Laravel migration file from an existing table (columns + indexes + FKs).</div>
            </div>
        </div>
    </div>

    {{-- Links --}}
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold">Links</div>
        <div class="px-4 py-3 text-sm flex flex-wrap gap-4">
            <a href="https://packagist.org/packages/mahmoud-mhamed/laravel-dblens" target="_blank" rel="noopener" class="text-sky-600 hover:underline">📦 Packagist</a>
            <a href="https://github.com/mahmoud-mhamed/laravel-dblens" target="_blank" rel="noopener" class="text-sky-600 hover:underline">🐙 GitHub</a>
            <span class="text-slate-400">·</span>
            <span class="text-xs text-slate-500">composer require mahmoud-mhamed/laravel-dblens</span>
        </div>
    </div>
</div>
@endsection
