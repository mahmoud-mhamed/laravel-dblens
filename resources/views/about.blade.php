@extends('dblens::layout')

@php
    $tenancy = app(\MahmoudMhamed\DbLens\Services\TenancyManager::class);
    $logger = app(\MahmoudMhamed\DbLens\Services\ActivityLogger::class);
    $logStats = $activity_log_stats ?? ['enabled' => false];

    $fmtBytes = function (int $b): string {
        if ($b <= 0) return '0 B';
        $u = ['B','KB','MB','GB','TB'];
        $i = (int) floor(log($b, 1024));
        return round($b / (1024 ** $i), $i ? 1 : 0) . ' ' . $u[$i];
    };
    $fmtNum = fn (int $n) => number_format($n);

    $driverLabel = match ($driver_name ?? null) {
        'mysql'   => 'MySQL',
        'mariadb' => 'MariaDB',
        'pgsql'   => 'PostgreSQL',
        'sqlite'  => 'SQLite',
        'sqlsrv'  => 'SQL Server',
        default   => ucfirst((string) ($driver_name ?? 'unknown')),
    };

    $gateAbility = config('dblens.gate');
    $gateRegistered = $gateAbility ? \Illuminate\Support\Facades\Gate::has($gateAbility) : false;
@endphp

@section('content')
<div class="space-y-6">
    {{-- Hero --}}
    <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-sky-50 via-white to-white p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <span>🔍</span> DbLens
                    <span class="text-xs font-semibold text-slate-500 bg-white border border-slate-200 rounded-full px-2 py-0.5 align-middle">v{{ $version ?? 'dev' }}</span>
                </h1>
                <p class="text-slate-600 text-sm mt-2 max-w-2xl leading-relaxed">
                    A phpMyAdmin-style browser for your Laravel database — built as a small Blade + Alpine package.
                    Browse tables, edit rows inline, run SQL, manage schema, view the ER diagram, and explore
                    views/triggers/procedures, all without leaving your app.
                </p>
                <div class="flex flex-wrap gap-2 mt-3 text-xs">
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">Laravel 10 → 13</span>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">PHP 8.1+</span>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">MySQL · PostgreSQL · SQLite · SQL Server</span>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded-full font-semibold text-slate-600">Tailwind · Alpine.js</span>
                </div>
            </div>
            <div class="text-right text-xs text-slate-500 mono shrink-0 leading-relaxed">
                <div>PHP {{ PHP_VERSION }}</div>
                <div>Laravel {{ app()->version() }}</div>
                <div>{{ $driverLabel }}{{ $server_version ? ' '.$server_version : '' }}</div>
            </div>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-slate-200 rounded p-4">
            <div class="text-[10px] uppercase text-slate-400">Active connection</div>
            <div class="font-bold text-slate-800 mt-1 truncate">{{ $connection ?? '—' }}</div>
            <div class="text-xs text-slate-500 mono truncate">{{ $database ?? '—' }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded p-4">
            <div class="text-[10px] uppercase text-slate-400">Tables</div>
            <div class="font-bold text-slate-800 mt-1">{{ $fmtNum(count($tables ?? [])) }}</div>
            <div class="text-xs text-slate-500">{{ count($connections ?? []) }} connection(s) allowed</div>
        </div>
        <div class="bg-white border border-slate-200 rounded p-4">
            <div class="text-[10px] uppercase text-slate-400">Total size</div>
            <div class="font-bold text-slate-800 mt-1">{{ $fmtBytes((int) ($total_size_bytes ?? 0)) }}</div>
            <div class="text-xs text-slate-500">{{ $driverLabel }} reported</div>
        </div>
        <div class="bg-white border border-slate-200 rounded p-4">
            <div class="text-[10px] uppercase text-slate-400">Mode</div>
            <div class="font-bold mt-1 {{ config('dblens.read_only') ? 'text-amber-600' : 'text-emerald-600' }}">
                {{ config('dblens.read_only') ? 'READ-ONLY' : 'READ / WRITE' }}
            </div>
            <div class="text-xs text-slate-500">
                SQL writes: {{ config('dblens.sql_editor.allow_writes') ? 'on' : 'off' }}
            </div>
        </div>
    </div>

    {{-- Two-column: Security + Activity log --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Security / Access --}}
        <div class="bg-white rounded shadow-sm border border-slate-200">
            <div class="px-4 py-3 border-b font-semibold flex items-center gap-2">
                <span>🛡</span> Security &amp; access
            </div>
            <div class="divide-y text-sm">
                @php
                    $passwordSet = (string) config('dblens.viewer.password', '') !== '';
                    $rows = [
                        ['Route prefix',         '/' . trim((string) config('dblens.viewer.path', 'dblens'), '/'),  null],
                        ['Domain restriction',   config('dblens.viewer.domain') ?: 'any',                          null],
                        ['Middleware chain',     implode(' → ', (array) config('dblens.viewer.middleware', [])),    null],
                        ['Password gate',        $passwordSet ? 'set' : 'not set',                                  $passwordSet ? 'emerald' : 'slate'],
                        ['Gate ability',         $gateAbility ?: '—',                                               $gateAbility && ! $gateRegistered ? 'amber' : null],
                        ['Gate registered',      $gateAbility ? ($gateRegistered ? 'yes' : 'no (using default)') : '—', null],
                        ['Throttle',             config('dblens.throttle.enabled', true) ? config('dblens.throttle.attempts').' / '.config('dblens.throttle.minutes').' min' : 'off', null],
                        ['Enabled in local',     config('dblens.enable_local') ? 'yes' : 'no',                      null],
                        ['Enabled in production', config('dblens.enable_production') ? 'yes' : 'no',                config('dblens.enable_production') && app()->environment('production') ? 'amber' : null],
                    ];
                @endphp
                @foreach ($rows as [$label, $value, $tone])
                    <div class="px-4 py-2 flex items-center justify-between gap-3">
                        <div class="text-xs text-slate-500">{{ $label }}</div>
                        @php $color = match($tone) { 'emerald' => 'text-emerald-600', 'amber' => 'text-amber-600', 'slate' => 'text-slate-400', default => 'text-slate-700' }; @endphp
                        <div class="mono text-xs font-semibold {{ $color }} truncate text-right max-w-[60%]">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Activity log --}}
        <div class="bg-white rounded shadow-sm border border-slate-200">
            <div class="px-4 py-3 border-b font-semibold flex items-center justify-between">
                <span class="flex items-center gap-2"><span>📜</span> Activity log</span>
                @if ($logStats['enabled'] ?? false)
                    <span class="text-[10px] uppercase font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-2 py-0.5">active</span>
                @elseif (class_exists(\Spatie\Activitylog\Models\Activity::class))
                    <span class="text-[10px] uppercase font-semibold bg-slate-50 text-slate-600 border border-slate-200 rounded-full px-2 py-0.5">available (off)</span>
                @else
                    <span class="text-[10px] uppercase font-semibold bg-slate-50 text-slate-400 border border-slate-200 rounded-full px-2 py-0.5">not installed</span>
                @endif
            </div>
            <div class="text-sm">
                @if ($logStats['enabled'] ?? false)
                    @if (isset($logStats['error']))
                        <div class="px-4 py-3 text-xs text-amber-700 bg-amber-50">{{ $logStats['error'] }}</div>
                    @else
                        <div class="grid grid-cols-3 divide-x border-b">
                            <div class="px-4 py-3">
                                <div class="text-[10px] uppercase text-slate-400">Total entries</div>
                                <div class="font-bold text-slate-800 mt-1">{{ $fmtNum((int) ($logStats['total'] ?? 0)) }}</div>
                            </div>
                            <div class="px-4 py-3">
                                <div class="text-[10px] uppercase text-slate-400">Last 24h</div>
                                <div class="font-bold text-slate-800 mt-1">{{ $fmtNum((int) ($logStats['last_24h'] ?? 0)) }}</div>
                            </div>
                            <div class="px-4 py-3">
                                <div class="text-[10px] uppercase text-slate-400">Latest</div>
                                <div class="font-bold text-slate-800 mt-1 text-xs mono truncate">{{ $logStats['latest_at'] ?? '—' }}</div>
                            </div>
                        </div>
                    @endif
                    <div class="px-4 py-3 space-y-1 text-xs text-slate-600">
                        <div>Log name: <span class="mono font-semibold">{{ $logStats['log_name'] ?? config('dblens.activity_log.log_name', 'dblens') }}</span></div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach ((array) config('dblens.activity_log.enrich', []) as $k => $on)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $on ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                                    {{ $k }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @elseif (class_exists(\Spatie\Activitylog\Models\Activity::class))
                    <div class="px-4 py-3 text-xs text-slate-600">
                        spatie/laravel-activitylog is installed but the <code class="mono">activity_log</code> table isn't reachable.
                        Run <code class="mono text-sky-700">php artisan migrate</code> then
                        <code class="mono text-sky-700">php artisan dblens:activitylog-install</code>.
                    </div>
                @else
                    <div class="px-4 py-3 text-xs text-slate-600">
                        Install <code class="mono">spatie/laravel-activitylog</code> to record every write — row CRUD, DDL,
                        SQL, imports — with structured enrichment (request, device, performance, app, session, execution).
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tenancy --}}
    @if ($tenancy->isAvailable())
        <div class="bg-white rounded shadow-sm border border-slate-200">
            <div class="px-4 py-3 border-b font-semibold flex items-center gap-2">
                <span>🏢</span> Multi-tenancy
            </div>
            <div class="px-4 py-3 text-sm">
                @if ($tenancy->isInitialized() && ($cur = $tenancy->current()))
                    Tenant <span class="mono font-semibold">{{ $cur['name'] ?? $cur['id'] }}</span> is initialized — DbLens reads its database transparently.
                @else
                    stancl/tenancy is installed (central context). DbLens auto-switches when routes run in tenant context.
                @endif
            </div>
        </div>
    @endif

    {{-- Features --}}
    <div>
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Features</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ([
                ['🗂', 'Table browser', 'Paginated rows with per-column inline filters, row-preview popover (search + not-null toggle), sort, search, bulk delete.'],
                ['✏️', 'Inline cell editor', 'Double-click any cell to edit in place — text, numbers, dates, enums, FK lookups, JSON modal with format/minify/validate, NULL button.'],
                ['🪶', 'Soft-delete aware', 'Rows with `deleted_at` highlighted red across browse + tree; one-click "Not deleted" filter, soft-delete option in confirm dialog, ↩ restore button.'],
                ['🌳', 'Tree view', 'Hierarchical view for self-referential tables with per-node child search, jump-to-row, browse-children buttons, expand/collapse all.'],
                ['🧱', 'Schema editor', 'Add / rename / drop columns and indexes, manage foreign keys, drop or truncate tables. Validated via FormRequest.'],
                ['⚡', 'SQL editor', 'Run any query with row-limit safety. EXPLAIN button for driver-aware query plans.'],
                ['🔗', 'Smart cell viewer', 'JSON tree, image preview (URL/base64), markdown, XML, URL detection — all rendered on the row page.'],
                ['🔁', 'Related rows', 'Row page shows actual related rows from each incoming FK with one-click navigation, not just counts.'],
                ['🗺️', 'ER diagram', 'Interactive zoom/pan/drag map; click arrows for FK details; star a table to focus, related-only mode auto-arranges neighbors.'],
                ['📁', 'Saved ER views', 'Persist a fully laid-out diagram (positions, zoom, toggles, active table) as JSON and reload in one click.'],
                ['🧩', 'DB objects', 'Browse views, stored routines, triggers, events. Drop them from the UI.'],
                ['📜', 'Activity log', 'Records every write through spatie/laravel-activitylog — row CRUD, DDL, SQL, imports — with structured enrichment (request/device/performance/app/session/execution) matching the spatie-activitylog-browse shape.'],
                ['🏢', 'Multi-tenancy', 'Auto-detects stancl/tenancy. Shows current tenant in the topbar and browses its DB transparently when routes run in tenant context.'],
                ['📥', 'Import / export', 'Per-table CSV / JSON / SQL export, CSV / SQL import. Configurable size cap on SQL dumps.'],
                ['🛡', 'Permissions', 'Read-only mode, per-action allow flags (truncate, drop, export, import), `viewDbLens` gate, login throttle.'],
                ['🩺', 'Friendly errors', 'Misconfigured drivers (e.g. wrong port/driver) render a clean 503 page instead of a stack trace.'],
                ['🎛', 'Drivers', 'MySQL, PostgreSQL, SQLite, and SQL Server drivers behind a shared interface with `castToText` + approximate-count abstractions.'],
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
        <div class="px-4 py-3 border-b font-semibold flex items-center gap-2"><span>⌨</span> Artisan commands</div>
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
                <code class="text-sky-700 mono">php artisan dblens:activitylog-install</code>
                <div class="text-xs text-slate-500 mt-1">Add composite indexes on the spatie <code class="mono">activity_log</code> table for fast browsing.</div>
            </div>
            <div class="px-4 py-3">
                <code class="text-sky-700 mono">php artisan dblens:make-migration {table}</code>
                <div class="text-xs text-slate-500 mt-1">Generate a Laravel migration file from an existing table (columns + indexes + FKs).</div>
            </div>
        </div>
    </div>

    {{-- Links --}}
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold flex items-center gap-2"><span>🔗</span> Links</div>
        <div class="px-4 py-3 text-sm flex flex-wrap items-center gap-4">
            <a href="https://packagist.org/packages/mahmoud-mhamed/laravel-dblens" target="_blank" rel="noopener" class="text-sky-600 hover:underline">📦 Packagist</a>
            <a href="https://github.com/mahmoud-mhamed/laravel-dblens" target="_blank" rel="noopener" class="text-sky-600 hover:underline">🐙 GitHub</a>
            <a href="https://github.com/mahmoud-mhamed/spatie-activitylog-browse" target="_blank" rel="noopener" class="text-sky-600 hover:underline">📜 activitylog-browse</a>
            <span class="text-slate-300">·</span>
            <code class="text-xs text-slate-500 mono">composer require mahmoud-mhamed/laravel-dblens</code>
        </div>
    </div>
</div>
@endsection
