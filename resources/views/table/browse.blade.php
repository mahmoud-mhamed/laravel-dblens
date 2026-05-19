@extends('dblens::layout')

@php
    $masked = array_map('strtolower', (array) config('dblens.masked_columns', []));
    $truncate = (int) config('dblens.browse.truncate_cell', 120);
    $readOnly = (bool) config('dblens.read_only', false);
    $hasPk = ! empty($primary_key);
    $columnNames = array_map(fn ($c) => $c['name'], $columns);
    $storageKey = 'dblens:' . $connection . ':' . $table . ':cols';

    $rowKey = function (array $row) use ($primary_key) {
        if (empty($primary_key)) return null;
        $vals = [];
        foreach ($primary_key as $c) { $vals[$c] = $row[$c] ?? null; }
        if (count($primary_key) === 1) {
            return rawurlencode((string) $vals[$primary_key[0]]);
        }
        return rawurlencode(json_encode($vals));
    };

    $ops = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL'];
    $cleanFilters = [];
    foreach ($filters as $f) {
        if (is_array($f) && ! empty($f['column'])) {
            $cleanFilters[] = [
                'column' => $f['column'],
                'op' => $f['op'] ?? '=',
                'value' => $f['value'] ?? '',
            ];
        }
    }
    $hasFilters = ! empty($cleanFilters);
@endphp

@section('content')
<div x-data="dbLensBrowse({
        columns: {{ json_encode($columnNames) }},
        storageKey: @js($storageKey),
        filters: {{ json_encode($cleanFilters) }},
        showFilters: {{ $hasFilters ? 'true' : 'false' }},
        ops: {{ json_encode($ops) }}
     })" x-init="init()">

    {{-- ─── Filters bar ─────────────────────────────────────────── --}}
    <div class="bg-white rounded shadow-sm border border-slate-200 mb-3 p-3">
        <form method="GET">
            <div class="flex flex-wrap gap-2 items-center">
                <input type="text" name="search" value="{{ $search }}" placeholder="🔍 Search in this table…"
                       class="flex-1 min-w-[200px] px-3 py-2 border border-slate-300 rounded text-sm">
                <button type="button" @click="showFilters = !showFilters"
                        class="px-3 py-2 border border-slate-300 rounded text-sm hover:bg-slate-50"
                        :class="filters.length ? 'bg-sky-50 border-sky-300 text-sky-700' : ''">
                    ⚙ Filters <span x-show="filters.length" x-text="`(${filters.length})`" class="ml-1"></span>
                </button>

                {{-- Column visibility --}}
                <div class="relative" @click.outside="showColsPanel = false">
                    <button type="button" @click="showColsPanel = !showColsPanel"
                            class="px-3 py-2 border border-slate-300 rounded text-sm hover:bg-slate-50"
                            :class="visibleCount() < columns.length ? 'bg-amber-50 border-amber-300 text-amber-700' : ''">
                        👁 Columns (<span x-text="visibleCount()"></span>/{{ count($columnNames) }})
                    </button>
                    <div x-show="showColsPanel" x-cloak
                         class="absolute right-0 mt-1 w-64 bg-white border border-slate-200 rounded-lg shadow-xl z-20 max-h-96 flex flex-col">
                        <div class="px-3 py-2 border-b bg-slate-50 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Visible columns</span>
                            <div class="text-xs flex gap-2">
                                <button type="button" @click="selectAllVisible(); persistCols()" class="text-sky-600 hover:underline" title="Select all matching the filter">All</button>
                                <button type="button" @click="clearAllVisible(); persistCols()" class="text-slate-500 hover:underline" title="Clear all matching the filter">None</button>
                            </div>
                        </div>
                        <div class="px-2 py-2 border-b">
                            <input type="text" x-model="colSearch" placeholder="🔍 Search columns…"
                                   class="w-full px-2 py-1 border border-slate-300 rounded text-sm">
                        </div>
                        <div class="p-1 overflow-y-auto flex-1 scroll-thin">
                            <template x-for="c in filteredColumns()" :key="c">
                                <label class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded cursor-pointer text-sm">
                                    <input type="checkbox" :value="c" x-model="visibleCols" @change="persistCols()">
                                    <span class="mono truncate" x-text="c"></span>
                                </label>
                            </template>
                            <div x-show="filteredColumns().length === 0" class="px-2 py-3 text-center text-xs text-slate-400">No columns match.</div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="per_page" value="{{ (int) request('per_page', config('dblens.browse.per_page')) }}">
                <input type="hidden" name="order_by" value="{{ $order_by }}">
                <input type="hidden" name="order_dir" value="{{ $order_dir }}">
                <button class="px-3 py-2 bg-sky-600 text-white rounded text-sm hover:bg-sky-700">Apply</button>
                @if ($search !== '' || $hasFilters)
                    <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table]) }}" class="text-xs text-slate-500 hover:underline">Clear</a>
                @endif
                <span class="text-xs text-slate-500 ml-auto">{{ number_format($total) }} row(s)</span>
                @unless ($readOnly)
                    <a href="{{ route('dblens.row.create', ['connection' => $connection, 'table' => $table]) }}"
                       class="px-3 py-2 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700">+ Insert</a>
                @endunless
            </div>

            <div x-show="showFilters" x-cloak class="mt-3 border-t pt-3 space-y-2">
                <template x-for="(f, i) in filters" :key="i">
                    <div class="flex flex-wrap gap-2 items-center">
                        <select :name="`filters[${i}][column]`" x-model="f.column" class="px-2 py-1 border border-slate-300 rounded text-sm mono w-44">
                            @foreach ($columns as $c)
                                <option value="{{ $c['name'] }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                        <select :name="`filters[${i}][op]`" x-model="f.op" class="px-2 py-1 border border-slate-300 rounded text-sm mono w-32">
                            <template x-for="op in ops" :key="op">
                                <option :value="op" x-text="op"></option>
                            </template>
                        </select>
                        <input :name="`filters[${i}][value]`" x-model="f.value" type="text"
                               :disabled="['IS NULL','IS NOT NULL'].includes(f.op)"
                               :placeholder="['IS NULL','IS NOT NULL'].includes(f.op) ? '— no value —' : 'value'"
                               class="flex-1 min-w-[160px] px-2 py-1 border border-slate-300 rounded text-sm mono disabled:bg-slate-100 disabled:text-slate-400">
                        <button type="button" @click="removeFilter(i)" class="text-red-500 hover:text-red-700 px-2" title="Remove">✕</button>
                    </div>
                </template>
                <button type="button" @click="addFilter()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-sm rounded text-slate-700">+ Add filter</button>
                <p class="text-xs text-slate-500">All filters are combined with <span class="mono">AND</span>. Use <span class="mono">LIKE</span> with <span class="mono">%</span> wildcards for substring match.</p>
            </div>
        </form>
    </div>

    {{-- ─── Bulk form / table ───────────────────────────────────── --}}
    <form method="POST" action="{{ route('dblens.row.bulk-destroy', ['connection' => $connection, 'table' => $table]) }}"
          x-data="{ selected: [] }" id="bulk-form"
          onsubmit="return confirm('Delete ' + this.querySelectorAll('input[name=\'keys[]\']:checked').length + ' row(s)? This cannot be undone.');">
        @csrf
        <input type="hidden" name="confirm" value="1">

        <div class="bg-white rounded shadow-sm border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        @if ($hasPk && ! $readOnly)
                            <th class="px-3 py-2 w-8">
                                <input type="checkbox" @click="
                                    const boxes = document.querySelectorAll('input[name=\'keys[]\']');
                                    boxes.forEach(b => b.checked = $event.target.checked);
                                    selected = [...boxes].filter(b => b.checked).map(b => b.value);
                                ">
                            </th>
                        @endif
                        <th class="px-3 py-2 text-left"></th>
                        @foreach ($columns as $c)
                            @php
                                $dir = ($order_by === $c['name'] && strtoupper($order_dir) === 'ASC') ? 'DESC' : 'ASC';
                                $isPk = in_array($c['name'], $primary_key, true);
                                $isFk = isset($foreign_keys[$c['name']]);
                                $isSorted = $order_by === $c['name'];
                                $sortIcon = $isSorted ? (strtoupper($order_dir) === 'ASC' ? '▲' : '▼') : '⇅';
                                $sortClass = $isSorted ? 'text-sky-600' : 'text-slate-300';
                            @endphp
                            <th class="px-3 py-2 text-left mono whitespace-nowrap" x-show="visible('{{ $c['name'] }}')">
                                <a href="{{ request()->fullUrlWithQuery(['order_by' => $c['name'], 'order_dir' => $dir]) }}"
                                   class="inline-flex items-center gap-1 hover:text-sky-600 group">
                                    <span>{{ $c['name'] }}</span>
                                    <span class="{{ $sortClass }} group-hover:text-sky-600 text-xs">{{ $sortIcon }}</span>
                                </a>
                                @if ($isPk) <span class="text-amber-600" title="Primary key">🔑</span> @endif
                                @if ($isFk) <span class="text-violet-600" title="FK → {{ $foreign_keys[$c['name']]['foreign_table'] }}.{{ $foreign_keys[$c['name']]['foreign_column'] }}">🔗</span> @endif
                                <div class="text-xs text-slate-400 font-normal">{{ $c['type'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $rk = $rowKey($row); @endphp
                        <tr class="border-t hover:bg-slate-50">
                            @if ($hasPk && ! $readOnly)
                                <td class="px-3 py-2">
                                    @if ($rk)
                                        <input type="checkbox" name="keys[]" value="{{ $rk }}" @change="selected = [...document.querySelectorAll('input[name=\'keys[]\']:checked')].map(b => b.value)">
                                    @endif
                                </td>
                            @endif
                            <td class="px-3 py-2 whitespace-nowrap text-xs">
                                @if ($rk)
                                    <a href="{{ route('dblens.row.show', ['connection' => $connection, 'table' => $table, 'rowKey' => $rk]) }}" class="text-sky-600 hover:underline">view</a>
                                    @unless ($readOnly)
                                        <span class="text-slate-300 mx-1">·</span>
                                        <a href="{{ route('dblens.row.edit', ['connection' => $connection, 'table' => $table, 'rowKey' => $rk]) }}" class="text-amber-600 hover:underline">edit</a>
                                    @endunless
                                @endif
                            </td>
                            @foreach ($columns as $c)
                                @php
                                    $val = $row[$c['name']] ?? null;
                                    $isMasked = in_array(strtolower($c['name']), $masked, true);
                                    $fk = $foreign_keys[$c['name']] ?? null;
                                    $isPkCol = in_array($c['name'], $primary_key, true);
                                    $type = strtolower($c['type']);
                                    $editable = ! $readOnly && ! $isMasked && ! $isPkCol && $rk !== null;
                                    $phpEnumCases = ($enum_casts ?? [])[$c['name']] ?? null;
                                    $kind = 'text';
                                    if ($fk) $kind = 'fk';
                                    elseif ($phpEnumCases) $kind = 'php_enum';
                                    elseif (preg_match('/^enum\((.*)\)$/i', $type)) $kind = 'enum';
                                    elseif (preg_match('/tinyint\(1\)|^bool/i', $type)) $kind = 'bool';
                                    elseif (preg_match('/text|json/i', $type)) $kind = 'textarea';
                                    elseif (preg_match('/datetime|timestamp/i', $type)) $kind = 'datetime';
                                    elseif (preg_match('/^date($|[^t])/i', $type)) $kind = 'date';
                                    elseif (preg_match('/int|decimal|numeric|float|double/i', $type)) $kind = 'number';
                                    $enumValues = [];
                                    if ($kind === 'enum' && preg_match("/^enum\\((.*)\\)$/i", $type, $m)) {
                                        foreach (explode(',', $m[1]) as $e) $enumValues[] = trim($e, " '\"");
                                    }
                                @endphp
                                <td class="px-3 py-2 mono {{ $editable ? '' : 'truncate-cell' }}"
                                    @if ($editable) :class="editing ? 'overflow-visible relative' : 'truncate-cell'" @endif
                                    x-show="visible('{{ $c['name'] }}')"
                                    @if ($editable)
                                        x-data="dbLensCell({
                                            saveUrl: '{{ route('dblens.row.cell.update', ['connection' => $connection, 'table' => $table]) }}',
                                            fkUrl: '{{ $fk ? route('dblens.row.fk.options', ['connection' => $connection, 'table' => $table]).'?column='.urlencode($c['name']) : '' }}',
                                            rowKey: @js($rk),
                                            column: @js($c['name']),
                                            kind: @js($kind),
                                            enumValues: @js($enumValues),
                                            phpEnumCases: @js($phpEnumCases ?? []),
                                            nullable: {{ $c['nullable'] ? 'true' : 'false' }},
                                            initial: @js($val)
                                        })"
                                    @endif>
                                    @if ($editable)
                                        <div x-show="!editing" @dblclick="startEdit()" class="cursor-pointer hover:bg-amber-50 -mx-1 px-1 rounded" title="Double-click to edit">
                                            <span x-show="original === null || original === ''" class="text-slate-400 italic">NULL</span>
                                            @if ($fk)
                                                <a x-show="original !== null && original !== ''"
                                                   :href="'{{ rtrim(route('dblens.table.browse', ['connection' => $connection, 'table' => $fk['foreign_table']]), '/') }}/r/' + encodeURIComponent(JSON.stringify({ {{ json_encode($fk['foreign_column']) }}: original }))"
                                                   @click.stop class="text-violet-600 hover:underline"
                                                   title="→ {{ $fk['foreign_table'] }}.{{ $fk['foreign_column'] }}"
                                                   x-text="String(original ?? '').slice(0, {{ $truncate }})"></a>
                                            @else
                                                <span x-show="original !== null && original !== ''" x-text="String(original ?? '').slice(0, {{ $truncate }})"></span>
                                            @endif
                                        </div>
                                        <div x-show="editing" x-cloak class="flex items-center gap-1">
                                            <template x-if="kind === 'fk'">
                                                <div class="relative flex-1 min-w-[200px]" @click.outside="fkOpen = false">
                                                    <input x-ref="input" type="text" x-model="fkSearch" :disabled="saving"
                                                           @focus="fkOpen = true"
                                                           @input.debounce.250ms="loadFkOptions(fkSearch)"
                                                           @keydown.escape.stop="cancel()"
                                                           @keydown.enter.prevent="save()"
                                                           class="w-full px-2 py-0.5 border border-sky-400 rounded text-xs mono"
                                                           placeholder="Search…">
                                                    <div x-show="fkOpen" x-cloak
                                                         class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl z-30 max-h-64 overflow-y-auto scroll-thin">
                                                        <template x-if="options === null">
                                                            <div class="px-3 py-2 text-xs text-slate-400" x-text="loadingLabel"></div>
                                                        </template>
                                                        <template x-if="options !== null && options.length === 0">
                                                            <div class="px-3 py-2 text-xs text-slate-400">No matches.</div>
                                                        </template>
                                                        @if ($c['nullable'])
                                                            <div @click="value = ''; fkSearch = '— NULL —'; fkOpen = false"
                                                                 class="px-3 py-1.5 text-xs italic text-slate-500 hover:bg-slate-50 cursor-pointer">— NULL —</div>
                                                        @endif
                                                        <template x-for="opt in (options || [])" :key="opt.value">
                                                            <div @click="value = String(opt.value); fkSearch = opt.label; fkOpen = false"
                                                                 :class="String(opt.value) === String(value) ? 'bg-sky-50 text-sky-700' : 'hover:bg-slate-50'"
                                                                 class="px-3 py-1.5 text-xs mono cursor-pointer">
                                                                <span x-text="opt.label"></span>
                                                                <span x-show="String(opt.value) === String(value)" class="float-right text-sky-600">✓</span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="kind === 'enum'">
                                                <select x-ref="input" x-model="value" :disabled="saving" class="px-1 py-0.5 border border-sky-400 rounded text-xs mono">
                                                    @if ($c['nullable']) <option value="">— NULL —</option> @endif
                                                    <template x-for="ev in enumValues" :key="ev"><option :value="ev" x-text="ev"></option></template>
                                                </select>
                                            </template>
                                            <template x-if="kind === 'php_enum'">
                                                <select x-ref="input" x-model="value" :disabled="saving" class="px-1 py-0.5 border border-violet-400 rounded text-xs mono" title="PHP enum cast">
                                                    @if ($c['nullable']) <option value="">— NULL —</option> @endif
                                                    <template x-for="c in phpEnumCases" :key="c.value">
                                                        <option :value="String(c.value)" x-text="c.label"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="kind === 'bool'">
                                                <select x-ref="input" x-model="value" :disabled="saving" class="px-1 py-0.5 border border-sky-400 rounded text-xs mono">
                                                    @if ($c['nullable']) <option value="">— NULL —</option> @endif
                                                    <option value="0">0</option>
                                                    <option value="1">1</option>
                                                </select>
                                            </template>
                                            <template x-if="kind === 'textarea'">
                                                <textarea x-ref="input" x-model="value" :disabled="saving" rows="3"
                                                          class="px-1 py-0.5 border border-sky-400 rounded text-xs mono w-full min-w-[200px]"
                                                          @keydown.escape="cancel()" @keydown.enter.ctrl.prevent="save()"></textarea>
                                            </template>
                                            <template x-if="kind === 'date'">
                                                <input x-ref="input" type="date" x-model="value" :disabled="saving"
                                                       class="px-1 py-0.5 border border-sky-400 rounded text-xs mono"
                                                       @keydown.escape="cancel()" @keydown.enter.prevent="save()">
                                            </template>
                                            <template x-if="kind === 'datetime'">
                                                <input x-ref="input" type="datetime-local" x-model="value" :disabled="saving"
                                                       class="px-1 py-0.5 border border-sky-400 rounded text-xs mono"
                                                       @keydown.escape="cancel()" @keydown.enter.prevent="save()">
                                            </template>
                                            <template x-if="kind === 'number'">
                                                <input x-ref="input" type="number" step="any" x-model="value" :disabled="saving"
                                                       class="px-1 py-0.5 border border-sky-400 rounded text-xs mono w-32"
                                                       @keydown.escape="cancel()" @keydown.enter.prevent="save()">
                                            </template>
                                            <template x-if="kind === 'text'">
                                                <input x-ref="input" type="text" x-model="value" :disabled="saving"
                                                       class="px-1 py-0.5 border border-sky-400 rounded text-xs mono w-full min-w-[160px]"
                                                       @keydown.escape="cancel()" @keydown.enter.prevent="save()">
                                            </template>
                                            <button type="button" @click="save()" :disabled="saving" class="px-1.5 text-emerald-600 hover:text-emerald-800" title="Save (Enter)">✓</button>
                                            <button type="button" @click="cancel()" :disabled="saving" class="px-1.5 text-slate-400 hover:text-slate-600" title="Cancel (Esc)">✕</button>
                                            <span x-show="error" x-text="error" class="text-xs text-red-600 ml-1"></span>
                                        </div>
                                    @else
                                        @if ($val === null)
                                            <span class="text-slate-400 italic">NULL</span>
                                        @elseif ($isMasked)
                                            <span class="text-slate-400">••••••</span>
                                        @elseif ($fk && $val !== null)
                                            @php $fkRowKey = rawurlencode(json_encode([$fk['foreign_column'] => $val])); @endphp
                                            <a href="{{ route('dblens.row.show', ['connection' => $connection, 'table' => $fk['foreign_table'], 'rowKey' => $fkRowKey]) }}"
                                               class="text-violet-600 hover:underline" title="→ {{ $fk['foreign_table'] }}.{{ $fk['foreign_column'] }}">
                                                {{ \Illuminate\Support\Str::limit((string) $val, $truncate) }}
                                            </a>
                                        @else
                                            {{ \Illuminate\Support\Str::limit((string) $val, $truncate) }}
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($columns) + ($hasPk && ! $readOnly ? 2 : 1) }}" class="px-3 py-6 text-center text-slate-400">No rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($hasPk && ! $readOnly)
            <div class="mt-3 flex items-center gap-3" x-show="selected.length > 0" x-cloak>
                <span class="text-sm text-slate-600"><span x-text="selected.length"></span> selected</span>
                <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Delete selected</button>
            </div>
        @endif
    </form>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except(['per_page', 'page']) as $qk => $qv)
                @if (is_array($qv))
                    @foreach ($qv as $i => $sub)
                        @if (is_array($sub))
                            @foreach ($sub as $sk => $sv)
                                <input type="hidden" name="{{ $qk }}[{{ $i }}][{{ $sk }}]" value="{{ $sv }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $qk }}[{{ $i }}]" value="{{ $sub }}">
                        @endif
                    @endforeach
                @else
                    <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                @endif
            @endforeach
            <label class="text-xs text-slate-500">Rows per page</label>
            <select name="per_page" onchange="this.form.submit()" class="px-2 py-1 border border-slate-300 rounded text-sm">
                @foreach (config('dblens.browse.per_page_options', [10,30,50,100,200]) as $p)
                    <option value="{{ $p }}" {{ (int) request('per_page', config('dblens.browse.per_page')) === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
        </form>
        <div>{{ $paginator->withQueryString()->links() }}</div>
    </div>

    <details class="mt-3 text-xs text-slate-500">
        <summary class="cursor-pointer">SQL</summary>
        <pre class="mt-2 p-3 bg-slate-50 border rounded overflow-x-auto mono">{{ $sql }}</pre>
    </details>
</div>

<script>
function dbLensBrowse(cfg) {
    return {
        columns: cfg.columns,
        storageKey: cfg.storageKey,
        filters: cfg.filters,
        showFilters: cfg.showFilters,
        ops: cfg.ops,
        showColsPanel: false,
        visibleCols: [],
        colSearch: '',

        init() {
            let restored = false;
            try {
                const saved = localStorage.getItem(this.storageKey);
                if (saved !== null) {
                    const arr = JSON.parse(saved);
                    if (Array.isArray(arr)) {
                        // Keep only columns that still exist in the table schema
                        this.visibleCols = arr.filter(c => this.columns.includes(c));
                        restored = true;
                    }
                }
            } catch (e) { /* ignore corrupt entry */ }
            if (! restored) {
                this.visibleCols = [...this.columns];
            }
        },
        persistCols() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.visibleCols));
            } catch (e) { /* storage full or disabled */ }
        },
        visible(col) { return this.visibleCols.includes(col); },
        visibleCount() { return this.visibleCols.length; },
        filteredColumns() {
            const q = (this.colSearch || '').trim().toLowerCase();
            return q === '' ? this.columns : this.columns.filter(c => c.toLowerCase().includes(q));
        },
        selectAllCols() { this.visibleCols = [...this.columns]; },
        clearAllCols() { this.visibleCols = []; },
        // All/None scoped to the current filter (so users with many columns
        // can tick or clear only the matched subset without losing the rest)
        selectAllVisible() {
            const matched = this.filteredColumns();
            const set = new Set([...this.visibleCols, ...matched]);
            this.visibleCols = [...set];
        },
        clearAllVisible() {
            const matched = new Set(this.filteredColumns());
            this.visibleCols = this.visibleCols.filter(c => ! matched.has(c));
        },
        addFilter() {
            this.filters.push({ column: this.columns[0] || '', op: '=', value: '' });
            this.showFilters = true;
        },
        removeFilter(i) { this.filters.splice(i, 1); },
    }
}

function dbLensCell(opts) {
    return {
        editing: false,
        saving: false,
        error: '',
        original: opts.initial,
        value: opts.initial === null ? '' : String(opts.initial),
        kind: opts.kind,
        enumValues: opts.enumValues || [],
        phpEnumCases: opts.phpEnumCases || [],
        nullable: !!opts.nullable,
        options: null,
        loadingLabel: 'Loading…',
        fkSearch: '',
        fkOpen: false,
        fkOpts: opts,
        async startEdit() {
            this.editing = true;
            this.error = '';
            this.value = this.original === null ? '' : String(this.original);
            if (this.kind === 'fk') {
                // Pre-fill search with the current value so the user sees it
                this.fkSearch = this.value === '' ? '' : String(this.value);
                this.fkOpen = true;
                await this.loadFkOptions('');
                // After options loaded, replace search with friendly label for current value
                const match = (this.options || []).find(o => String(o.value) === String(this.value));
                if (match) this.fkSearch = match.label;
            }
            this.$nextTick(() => this.$refs.input?.focus());
        },
        async loadFkOptions(q) {
            if (this.kind !== 'fk') return;
            this.options = null;
            try {
                const url = this.fkOpts.fkUrl + (this.fkOpts.fkUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q || '');
                const r = await fetch(url, { credentials: 'same-origin' });
                const data = await r.json();
                this.options = data.options || [];
            } catch (e) {
                this.options = [];
                this.error = 'Failed to load options.';
            }
        },
        async save() {
            if (this.saving) return;
            if (String(this.value) === String(this.original ?? '')) { this.editing = false; return; }
            this.saving = true;
            this.error = '';
            try {
                const payload = {
                    row_key: opts.rowKey,
                    column: opts.column,
                    value: this.value === '' && this.nullable ? null : this.value,
                };
                const r = await fetch(opts.saveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.message || ('HTTP ' + r.status));
                this.original = payload.value;
                this.editing = false;
                // simple visual feedback
                this.$el.classList.add('bg-emerald-50');
                setTimeout(() => this.$el.classList.remove('bg-emerald-50'), 700);
            } catch (e) {
                this.error = e.message;
            }
            this.saving = false;
        },
        cancel() {
            this.value = this.original === null ? '' : String(this.original);
            this.editing = false;
            this.error = '';
        },
    }
}
</script>
@endsection
