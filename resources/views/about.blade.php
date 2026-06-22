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
    @php
        $tnEnabledCfg = config('dblens.tenancy.enabled', 'auto');
        $tnEnabledLabel = is_bool($tnEnabledCfg) ? ($tnEnabledCfg ? 'true' : 'false') : (string) $tnEnabledCfg;
        $tnMiddleware = (array) config('dblens.tenancy.identification_middleware', []);
        $tnAvailable = $tenancy->isAvailable();
        $tnRows = [
            ['Detection mode',   $tnEnabledLabel, $tnEnabledCfg === false ? 'slate' : null],
            ['stancl/tenancy',   $tnAvailable ? 'installed' : 'not installed', $tnAvailable ? 'emerald' : 'slate'],
            ['Connection name',  config('dblens.tenancy.connection_name', 'tenant'), null],
        ];
    @endphp
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold flex items-center justify-between">
            <span class="flex items-center gap-2"><span>🏢</span> Multi-tenancy</span>
            @if ($tnAvailable && $tenancy->isInitialized() && ($cur = $tenancy->current()))
                <span class="text-[10px] uppercase font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-2 py-0.5">
                    tenant: {{ $cur['name'] ?? $cur['id'] }}
                </span>
            @elseif ($tnAvailable)
                <span class="text-[10px] uppercase font-semibold bg-slate-50 text-slate-600 border border-slate-200 rounded-full px-2 py-0.5">central context</span>
            @else
                <span class="text-[10px] uppercase font-semibold bg-slate-50 text-slate-400 border border-slate-200 rounded-full px-2 py-0.5">not installed</span>
            @endif
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x">
            <div class="divide-y">
                @foreach ($tnRows as [$label, $value, $tone])
                    <div class="px-4 py-2 flex items-center justify-between gap-3">
                        <div class="text-xs text-slate-500">{{ $label }}</div>
                        @php $color = match($tone) { 'emerald' => 'text-emerald-600', 'amber' => 'text-amber-600', 'slate' => 'text-slate-400', default => 'text-slate-700' }; @endphp
                        <div class="mono text-xs font-semibold {{ $color }} truncate text-right max-w-[60%]">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            <div class="px-4 py-3">
                <div class="text-[10px] uppercase text-slate-400 mb-2">Identification middleware</div>
                @if ($tnMiddleware)
                    <div class="flex flex-wrap gap-1">
                        @foreach ($tnMiddleware as $mw)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-sky-50 text-sky-700 border-sky-200 mono">{{ class_basename($mw) }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-500 leading-relaxed">
                        None set — DbLens is assumed to run on the central domain. Add tenant identification middleware
                        (e.g. <code class="mono">InitializeTenancyByDomain</code>) so the auth guard runs against the tenant DB.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Settings --}}
    <div>
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Settings</h2>
        @php
            // [value, tone] for a boolean. $dangerWhenOn flips an enabled flag to amber (heads-up) instead of emerald.
            $onOff = fn ($v, $dangerWhenOn = false) => $v
                ? [$dangerWhenOn ? 'enabled' : 'on', $dangerWhenOn ? 'amber' : 'emerald']
                : [$dangerWhenOn ? 'disabled' : 'off', 'slate'];

            [$roVal, $roTone]       = $onOff(config('dblens.read_only'), true);
            [$cdVal, $cdTone]       = $onOff(config('dblens.confirm_destructive', true));
            [$truncVal, $truncTone] = $onOff(config('dblens.allow_truncate'), true);
            [$delAllVal, $delAllTone] = $onOff(config('dblens.allow_delete_all'), true);
            [$dropVal, $dropTone]   = $onOff(config('dblens.allow_drop_table'), true);
            [$expVal, $expTone]     = $onOff(config('dblens.allow_export', true));
            [$impVal, $impTone]     = $onOff(config('dblens.allow_import', true));
            [$hashVal, $hashTone]   = $onOff(config('dblens.auto_hash', true));

            [$sqlEnVal, $sqlEnTone] = $onOff(config('dblens.sql_editor.enabled', true));
            [$sqlWrVal, $sqlWrTone] = $onOff(config('dblens.sql_editor.allow_writes'), true);

            $cards = [
                ['🔐', 'Permissions & safety', [
                    ['Read-only mode',      $roVal,    $roTone],
                    ['Confirm destructive', $cdVal,    $cdTone],
                    ['Allow truncate',      $truncVal, $truncTone],
                    ['Allow delete-all',    $delAllVal, $delAllTone],
                    ['Allow drop table',    $dropVal,  $dropTone],
                    ['Allow export',        $expVal,   $expTone],
                    ['Allow import',        $impVal,   $impTone],
                    ['Auto-hash columns',   $hashVal,  $hashTone],
                ]],
                ['⚡', 'SQL editor', [
                    ['Enabled',      $sqlEnVal, $sqlEnTone],
                    ['Allow writes', $sqlWrVal, $sqlWrTone],
                    ['Max rows',     $fmtNum((int) config('dblens.sql_editor.max_rows', 1000)),    null],
                    ['Timeout',      config('dblens.sql_editor.timeout_seconds', 30) . 's',         null],
                ]],
                ['🗂', 'Browse & rows', [
                    ['Rows per page',       $fmtNum((int) config('dblens.browse.per_page', 30)),               null],
                    ['Per-page options',    implode(' · ', (array) config('dblens.browse.per_page_options', [])), null],
                    ['Cell truncate',       config('dblens.browse.truncate_cell', 120) . ' chars',             null],
                    ['Approx-count after',  $fmtNum((int) config('dblens.browse.approx_count_threshold', 0)) . ' rows', null],
                    ['FK picker limit',     $fmtNum((int) config('dblens.browse.fk_options_limit', 100)),      null],
                    ['Related preview',     $fmtNum((int) config('dblens.row.related_preview_limit', 5)) . ' rows / FK', null],
                ]],
                ['📥', 'Import limits', [
                    ['Max SQL upload', ((int) config('dblens.import.max_sql_bytes', 0)) > 0 ? $fmtBytes((int) config('dblens.import.max_sql_bytes')) : 'no cap', null],
                    ['CSV batch size', $fmtNum((int) config('dblens.import.csv_batch_size', 200)) . ' rows', null],
                ]],
                ['🔎', 'Search & cache', [
                    ['Global search tables', ((int) config('dblens.global_search.max_tables', 0)) > 0 ? $fmtNum((int) config('dblens.global_search.max_tables')) . ' max' : 'no limit', null],
                    ['Search timeout',       config('dblens.global_search.statement_timeout_ms', 2000) . ' ms', null],
                    ['Rows per match',       $fmtNum((int) config('dblens.global_search.per_table_limit', 5)),  null],
                    ['Schema cache TTL',     config('dblens.schema_cache.ttl_seconds', 60) . 's',               null],
                ]],
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($cards as [$icon, $title, $rows])
                <div class="bg-white rounded shadow-sm border border-slate-200">
                    <div class="px-4 py-3 border-b font-semibold flex items-center gap-2 text-sm">
                        <span>{{ $icon }}</span> {{ $title }}
                    </div>
                    <div class="divide-y">
                        @foreach ($rows as [$label, $value, $tone])
                            <div class="px-4 py-2 flex items-center justify-between gap-3">
                                <div class="text-xs text-slate-500">{{ $label }}</div>
                                @php $color = match($tone) { 'emerald' => 'text-emerald-600', 'amber' => 'text-amber-600', 'slate' => 'text-slate-400', default => 'text-slate-700' }; @endphp
                                <div class="mono text-xs font-semibold {{ $color }} truncate text-right max-w-[60%]">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Activity log settings (detailed) --}}
    @php
        $alEnabledCfg = config('dblens.activity_log.enabled', 'auto');
        $alEnabledLabel = is_bool($alEnabledCfg) ? ($alEnabledCfg ? 'true' : 'false') : (string) $alEnabledCfg;
        [$capOldVal, $capOldTone] = $onOff(config('dblens.activity_log.capture_old_values', true));
        [$capSqlVal, $capSqlTone] = $onOff(config('dblens.activity_log.capture_sql', false), true);
        $alGuards  = (array) config('dblens.activity_log.guards', []);
        $alEvents  = (array) config('dblens.activity_log.events', []);
        $alRedact  = (array) config('dblens.activity_log.redact_columns', []);
        $alEnrich  = (array) config('dblens.activity_log.enrich', []);
        $alScalars = [
            ['Integration',      $alEnabledLabel, $alEnabledCfg === false ? 'slate' : null],
            ['Log name',         config('dblens.activity_log.log_name', 'dblens'), null],
            ['Log connection',   config('dblens.activity_log.connection') ?: 'spatie default', null],
            ['Causer guards',    $alGuards ? implode(' · ', $alGuards) : 'all auth guards', null],
            ['Capture old values', $capOldVal, $capOldTone],
            ['Capture raw SQL',  $capSqlVal, $capSqlTone],
            ['Max value length', $fmtNum((int) config('dblens.activity_log.max_value_length', 5000)) . ' chars', null],
        ];
    @endphp
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold flex items-center gap-2">
            <span>📜</span> Activity log settings
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x">
            {{-- Scalar config --}}
            <div class="divide-y">
                @foreach ($alScalars as [$label, $value, $tone])
                    <div class="px-4 py-2 flex items-center justify-between gap-3">
                        <div class="text-xs text-slate-500">{{ $label }}</div>
                        @php $color = match($tone) { 'emerald' => 'text-emerald-600', 'amber' => 'text-amber-600', 'slate' => 'text-slate-400', default => 'text-slate-700' }; @endphp
                        <div class="mono text-xs font-semibold {{ $color }} truncate text-right max-w-[60%]">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            {{-- Lists: events, enrichment, redacted columns --}}
            <div class="divide-y">
                <div class="px-4 py-3">
                    <div class="text-[10px] uppercase text-slate-400 mb-2">Logged events</div>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($alEvents as $event => $on)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border mono {{ $on ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200 line-through' }}">
                                {{ $event }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-400">none configured</span>
                        @endforelse
                    </div>
                </div>
                <div class="px-4 py-3">
                    <div class="text-[10px] uppercase text-slate-400 mb-2">Enrichment sections</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($alEnrich as $k => $on)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $on ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                                {{ $k }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="px-4 py-3">
                    <div class="text-[10px] uppercase text-slate-400 mb-2">Redacted columns</div>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($alRedact as $col)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-rose-50 text-rose-700 border-rose-200 mono">{{ $col }}</span>
                        @empty
                            <span class="text-xs text-slate-400">none</span>
                        @endforelse
                    </div>
                </div>
            </div>
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
                <code class="text-sky-700 mono">php artisan dblens:publish-config --force</code>
                <div class="text-xs text-slate-500 mt-1">Publish (or overwrite with <code class="mono">--force</code>) the config file at <code class="mono">config/dblens.php</code>.</div>
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
