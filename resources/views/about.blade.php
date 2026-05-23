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
                ['🗂', 'Table browser', 'Paginated rows with inline header filters, full-row hover preview, sort, search, and bulk delete.'],
                ['✏️', 'Inline cell editor', 'Double-click any cell to edit in place — text, numbers, dates, enums, JSON, booleans, FK lookups.'],
                ['🧱', 'Schema editor', 'Add / rename / drop columns and indexes, manage foreign keys, drop or truncate tables.'],
                ['⚡', 'SQL editor', 'Run any query with row-limit safety. EXPLAIN button for driver-aware query plans.'],
                ['🔗', 'Smart cell viewer', 'JSON tree, image preview (URL or base64), markdown, XML — all rendered on the row page.'],
                ['🗺️', 'ER diagram', 'Drag-to-arrange tables, click an arrow to inspect the FK, save/load custom layouts as JSON.'],
                ['🧩', 'DB objects', 'Browse views, stored routines, triggers, and events. Drop them from the UI.'],
                ['📥', 'Import / export', 'Per-table CSV / JSON / SQL export and SQL/CSV import.'],
                ['🛡', 'Permissions', 'Granular gates per action (browse, edit, ddl, drop, truncate, export, import).'],
                ['📁', 'Saved ER views', 'Persist a fully laid-out diagram (active table, positions, toggles) and reload it in one click.'],
                ['🎛', 'Drivers', 'First-class MySQL, PostgreSQL, and SQLite drivers with shared interface.'],
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
