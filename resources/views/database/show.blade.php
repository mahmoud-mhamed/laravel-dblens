@extends('dblens::layout')

@php
    $statTotalRows = array_sum(array_column($tables, 'rows'));
    $statTotalSize = array_sum(array_column($tables, 'size'));
    $statEmpty = count(array_filter($tables, fn ($t) => (int) $t['rows'] === 0));
    $statLargest = null;
    foreach ($tables as $t) {
        if ($statLargest === null || $t['size'] > $statLargest['size']) $statLargest = $t;
    }
    $statMostRows = null;
    foreach ($tables as $t) {
        if ($statMostRows === null || $t['rows'] > $statMostRows['rows']) $statMostRows = $t;
    }
    $fmtSize = function ($bytes) {
        $b = (float) $bytes;
        if ($b < 1024) return $b . ' B';
        if ($b < 1024 * 1024) return number_format($b / 1024, 1) . ' KB';
        if ($b < 1024 * 1024 * 1024) return number_format($b / 1024 / 1024, 1) . ' MB';
        return number_format($b / 1024 / 1024 / 1024, 2) . ' GB';
    };
@endphp

@section('content')
<div x-data="dbLensExportModal({{ json_encode(array_map(fn ($t) => ['name' => $t['name'], 'rows' => $t['rows'], 'size' => $t['size']], $tables)) }})">

    {{-- ─── Database stats ──────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded shadow-sm border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Tables</div>
            <div class="text-2xl font-bold mono mt-1">{{ number_format(count($tables)) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ number_format($statEmpty) }} empty</div>
        </div>
        <div class="bg-white rounded shadow-sm border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Total rows</div>
            <div class="text-2xl font-bold mono mt-1">{{ number_format($statTotalRows) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">approx.</div>
        </div>
        <div class="bg-white rounded shadow-sm border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Total size</div>
            <div class="text-2xl font-bold mono mt-1">{{ $fmtSize($statTotalSize) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">data + indexes</div>
        </div>
        <div class="bg-white rounded shadow-sm border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Largest table</div>
            @if ($statLargest)
                <a href="{{ route('dblens.table.info', ['connection' => $connection, 'table' => $statLargest['name']]) }}"
                   class="text-lg font-semibold mono mt-1 block truncate text-sky-600 hover:underline" title="{{ $statLargest['name'] }}">
                    {{ $statLargest['name'] }}
                </a>
                <div class="text-[11px] text-slate-400 mt-0.5">{{ $fmtSize($statLargest['size']) }}</div>
            @else
                <div class="text-lg mono mt-1 text-slate-400">—</div>
            @endif
        </div>
        <div class="bg-white rounded shadow-sm border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Most rows</div>
            @if ($statMostRows)
                <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $statMostRows['name']]) }}"
                   class="text-lg font-semibold mono mt-1 block truncate text-sky-600 hover:underline" title="{{ $statMostRows['name'] }}">
                    {{ $statMostRows['name'] }}
                </a>
                <div class="text-[11px] text-slate-400 mt-0.5">{{ number_format($statMostRows['rows']) }} rows</div>
            @else
                <div class="text-lg mono mt-1 text-slate-400">—</div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded shadow-sm border border-slate-200" x-data="dbLensTablesList({{ json_encode(array_column($tables, 'name')) }})">
        <div class="px-4 py-3 border-b flex items-center justify-between gap-3 flex-wrap">
            <h1 class="font-semibold">Tables in <span class="mono">{{ $database }}</span></h1>
            <input type="text" x-model="tableSearch" placeholder="🔍 Search tables…"
                   class="flex-1 min-w-[180px] max-w-md px-3 py-1.5 border border-slate-300 rounded text-sm">
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500 mr-2">
                    <span x-show="tableSearch === ''">{{ count($tables) }} table(s)</span>
                    <span x-show="tableSearch !== ''" x-cloak>
                        <span x-text="matchCount()"></span> / {{ count($tables) }} matched
                    </span>
                </span>
                @if (config('dblens.allow_export', true))
                    <a href="{{ route('dblens.database.export', ['connection' => $connection]) }}"
                       class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm hover:bg-slate-800">⬇ Export DB</a>
                    <button type="button" @click="open = true"
                            class="px-3 py-1.5 bg-slate-700 text-white rounded text-sm hover:bg-slate-800">⬇ Export Advanced</button>
                @endif
                @unless (config('dblens.read_only'))
                    @if (config('dblens.allow_import', true))
                        <a href="{{ route('dblens.database.import.form', ['connection' => $connection]) }}"
                           class="px-3 py-1.5 bg-slate-200 text-slate-700 rounded text-sm hover:bg-slate-300">⬆ Import</a>
                    @endif
                    <a href="{{ route('dblens.table.create.form', ['connection' => $connection]) }}"
                       class="px-3 py-1.5 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700">+ Create table</a>
                @endunless
            </div>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
                <tr>
                    <th class="px-4 py-2 cursor-pointer hover:text-sky-600 select-none" @click="toggleListSort('name')">
                        Table <span class="ml-1 text-xs" :class="listSortKey === 'name' ? 'text-sky-600' : 'text-slate-300'" x-text="listIcon('name')"></span>
                    </th>
                    <th class="px-4 py-2 text-right cursor-pointer hover:text-sky-600 select-none" @click="toggleListSort('rows')">
                        Rows <span class="ml-1 text-xs" :class="listSortKey === 'rows' ? 'text-sky-600' : 'text-slate-300'" x-text="listIcon('rows')"></span>
                    </th>
                    <th class="px-4 py-2 text-right cursor-pointer hover:text-sky-600 select-none" @click="toggleListSort('size')">
                        Size <span class="ml-1 text-xs" :class="listSortKey === 'size' ? 'text-sky-600' : 'text-slate-300'" x-text="listIcon('size')"></span>
                    </th>
                    <th class="px-4 py-2">Engine</th>
                    <th class="px-4 py-2">Collation</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody x-ref="rowsBody">
                @forelse ($tables as $t)
                    <tr class="border-t hover:bg-slate-50"
                        data-row
                        data-name="{{ $t['name'] }}"
                        data-rows="{{ (int) $t['rows'] }}"
                        data-size="{{ (int) $t['size'] }}"
                        x-show="tableSearch === '' || $el.dataset.name.toLowerCase().includes(tableSearch.toLowerCase())">
                        <td class="px-4 py-2 mono">
                            <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $t['name']]) }}" class="text-sky-600 hover:underline">{{ $t['name'] }}</a>
                            @if ($t['comment']) <span class="ml-2 text-xs text-slate-400" title="{{ $t['comment'] }}">💬</span> @endif
                        </td>
                        <td class="px-4 py-2 text-right mono">{{ number_format($t['rows']) }}</td>
                        <td class="px-4 py-2 text-right mono text-slate-500">
                            @if ($t['size']) {{ $fmtSize($t['size']) }} @else - @endif
                        </td>
                        <td class="px-4 py-2 text-slate-500">{{ $t['engine'] ?? '-' }}</td>
                        <td class="px-4 py-2 text-slate-500 text-xs">{{ $t['collation'] ?? '-' }}</td>
                        <td class="px-4 py-2 text-right text-xs whitespace-nowrap">
                            <a href="{{ route('dblens.table.structure', ['connection' => $connection, 'table' => $t['name']]) }}" class="text-slate-500 hover:text-sky-600">structure</a>
                            <span class="text-slate-300 mx-1">·</span>
                            <a href="{{ route('dblens.table.info', ['connection' => $connection, 'table' => $t['name']]) }}" class="text-slate-500 hover:text-sky-600">info</a>
                            @if (! config('dblens.read_only') && config('dblens.allow_truncate', true))
                                <span class="text-slate-300 mx-1">·</span>
                                <form method="POST" action="{{ route('dblens.table.truncate', ['connection' => $connection, 'table' => $t['name']]) }}"
                                      class="inline"
                                      data-confirm-title="Truncate table"
                                      data-confirm="TRUNCATE TABLE will remove ALL rows from [{{ $t['name'] }}]. This cannot be undone."
                                      data-confirm-text="Truncate"
                                      data-confirm-type="{{ $t['name'] }}">
                                    @csrf
                                    <input type="hidden" name="confirm" value="1">
                                    <button type="submit" class="text-amber-600 hover:underline" title="Truncate {{ $t['name'] }}">truncate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No tables.</td></tr>
                @endforelse
                <tr x-show="tableSearch !== '' && matchCount() === 0">
                    <td colspan="6" class="px-4 py-6 text-center text-slate-400 text-sm">No tables match your search.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ─── Advanced Export Modal ───────────────────────────────── --}}
    <div x-show="open" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
        <form method="POST" action="{{ route('dblens.database.export.custom', ['connection' => $connection]) }}"
              @click.outside="open = false"
              @submit="serialize()"
              class="bg-white rounded-lg shadow-2xl w-full max-w-3xl max-h-[88vh] flex flex-col">
            @csrf
            <input type="hidden" name="tables_structure_json" :value="JSON.stringify(structureSelected())">
            <input type="hidden" name="tables_data_json" :value="JSON.stringify(dataSelected())">

            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h3 class="font-semibold">Custom Export</h3>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
            </div>

            <div class="px-5 py-3 border-b bg-slate-50 flex flex-wrap gap-2 items-center text-xs">
                <div class="flex items-center gap-1 mr-1">
                    <span class="text-slate-500 font-semibold">Format:</span>
                    <label class="flex items-center gap-1 cursor-pointer px-2 py-1 rounded" :class="format === 'sql' ? 'bg-sky-100 text-sky-700' : 'hover:bg-slate-200'">
                        <input type="radio" name="format" value="sql" x-model="format" class="sr-only"> SQL
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer px-2 py-1 rounded" :class="format === 'csv' ? 'bg-sky-100 text-sky-700' : 'hover:bg-slate-200'">
                        <input type="radio" name="format" value="csv" x-model="format" class="sr-only"> CSV (zip)
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer px-2 py-1 rounded" :class="format === 'json' ? 'bg-sky-100 text-sky-700' : 'hover:bg-slate-200'">
                        <input type="radio" name="format" value="json" x-model="format" class="sr-only"> JSON
                    </label>
                </div>
                <input type="text" x-model="search" placeholder="Filter tables…" class="flex-1 min-w-[180px] px-2 py-1 border border-slate-300 rounded">
                <span class="text-slate-500">
                    <span x-text="structureSelected().length"></span> structure ·
                    <span x-text="dataSelected().length"></span> data
                </span>
                <button type="button" @click="setAll('s', true); setAll('d', true)" class="px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded">All ✓ ✓</button>
                <button type="button" @click="setAll('s', false); setAll('d', true)" class="px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded">Data only</button>
                <button type="button" @click="setAll('s', true); setAll('d', false)" class="px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded">Structure only</button>
                <button type="button" @click="setAll('s', false); setAll('d', false)" class="px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded">None</button>
            </div>

            <div class="flex-1 overflow-y-auto scroll-thin">
                <table class="w-full text-sm">
                    <thead class="bg-white sticky top-0 z-10 border-b text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left cursor-pointer hover:text-sky-600 select-none" @click="toggleSort('name')">
                                Table
                                <span class="ml-1" x-text="sortIcon('name')"></span>
                            </th>
                            <th class="px-3 py-2 text-right cursor-pointer hover:text-sky-600 select-none" @click="toggleSort('rows')">
                                Rows
                                <span class="ml-1" x-text="sortIcon('rows')"></span>
                            </th>
                            <th class="px-3 py-2 text-right cursor-pointer hover:text-sky-600 select-none" @click="toggleSort('size')">
                                Size
                                <span class="ml-1" x-text="sortIcon('size')"></span>
                            </th>
                            <th class="px-3 py-2 text-center" x-show="format === 'sql'">
                                Structure<br>
                                <input type="checkbox" @change="setAll('s', $event.target.checked)" :checked="allOn('s')">
                            </th>
                            <th class="px-3 py-2 text-center">
                                Data<br>
                                <input type="checkbox" @change="setAll('d', $event.target.checked)" :checked="allOn('d')">
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="t in filteredTables()" :key="t.name">
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 mono" x-text="t.name"></td>
                                <td class="px-3 py-2 text-right text-slate-500 mono" x-text="t.rows ? t.rows.toLocaleString() : '—'"></td>
                                <td class="px-3 py-2 text-right text-slate-500 mono" x-text="t.size ? formatBytes(t.size) : '—'"></td>
                                <td class="px-3 py-2 text-center" x-show="format === 'sql'"><input type="checkbox" x-model="picks[t.name].s"></td>
                                <td class="px-3 py-2 text-center"><input type="checkbox" x-model="picks[t.name].d"></td>
                            </tr>
                        </template>
                        <tr x-show="filteredTables().length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-400 text-xs">No tables match the filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t bg-slate-50 flex justify-between items-center gap-2">
                <p class="text-xs text-slate-500" x-show="format !== 'sql'" x-cloak>
                    <span x-text="format.toUpperCase()"></span> export contains data only — Structure choice is not used.
                </p>
                <p class="text-xs text-slate-500" x-show="format === 'sql'"></p>
                <div class="flex gap-2">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                    <button type="submit"
                            :disabled="!canSubmit()"
                            class="px-4 py-2 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        ⬇ Export <span x-text="format.toUpperCase()"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function dbLensTablesList(allNames) {
    return {
        tableSearch: '',
        allNames: allNames || [],
        listSortKey: 'name',
        listSortDir: 'asc',

        matchCount() {
            const q = this.tableSearch.toLowerCase();
            return q === '' ? this.allNames.length : this.allNames.filter(n => n.toLowerCase().includes(q)).length;
        },
        toggleListSort(k) {
            if (this.listSortKey === k) {
                this.listSortDir = this.listSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.listSortKey = k;
                this.listSortDir = (k === 'name') ? 'asc' : 'desc';
            }
            this.applyListSort();
        },
        listIcon(k) {
            if (this.listSortKey !== k) return '⇅';
            return this.listSortDir === 'asc' ? '▲' : '▼';
        },
        applyListSort() {
            const tbody = this.$refs.rowsBody;
            if (!tbody) { console.warn('dblens: rowsBody ref missing'); return; }
            const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
            if (rows.length === 0) { console.warn('dblens: no data-row found'); return; }
            const dir = this.listSortDir === 'asc' ? 1 : -1;
            const key = this.listSortKey;
            rows.sort((a, b) => {
                const va = a.dataset[key];
                const vb = b.dataset[key];
                if (key === 'name') {
                    return dir * String(va || '').localeCompare(String(vb || ''));
                }
                return dir * ((parseFloat(va) || 0) - (parseFloat(vb) || 0));
            });
            rows.forEach(r => tbody.appendChild(r));
        },
    }
}

function dbLensExportModal(tables) {
    const picks = {};
    tables.forEach(t => picks[t.name] = { s: true, d: true });
    return {
        open: false,
        tables,
        picks,
        search: '',
        format: 'sql',
        sortKey: 'name',
        sortDir: 'asc',
        canSubmit() {
            if (this.format === 'sql') {
                return this.structureSelected().length > 0 || this.dataSelected().length > 0;
            }
            return this.dataSelected().length > 0;
        },
        toggleSort(key) {
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = key === 'name' ? 'asc' : 'desc';
            }
        },
        sortIcon(key) {
            if (this.sortKey !== key) return '⇅';
            return this.sortDir === 'asc' ? '▲' : '▼';
        },
        filteredTables() {
            const q = this.search.trim().toLowerCase();
            let list = q === '' ? [...this.tables] : this.tables.filter(t => t.name.toLowerCase().includes(q));
            const key = this.sortKey, dir = this.sortDir === 'asc' ? 1 : -1;
            list.sort((a, b) => {
                let va = a[key], vb = b[key];
                if (key === 'name') return dir * String(va).localeCompare(String(vb));
                return dir * ((Number(va) || 0) - (Number(vb) || 0));
            });
            return list;
        },
        structureSelected() { return Object.keys(this.picks).filter(k => this.picks[k].s); },
        dataSelected() { return Object.keys(this.picks).filter(k => this.picks[k].d); },
        setAll(kind, on) {
            this.filteredTables().forEach(t => this.picks[t.name][kind] = on);
        },
        allOn(kind) {
            const list = this.filteredTables();
            return list.length > 0 && list.every(t => this.picks[t.name][kind]);
        },
        formatBytes(b) {
            if (b < 1024) return b + ' B';
            if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
            if (b < 1024*1024*1024) return (b/(1024*1024)).toFixed(1) + ' MB';
            return (b/(1024*1024*1024)).toFixed(2) + ' GB';
        },
        serialize() { /* hidden inputs already bound via :value */ }
    }
}
</script>
@endsection
