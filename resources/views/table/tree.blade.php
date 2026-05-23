@extends('dblens::layout')

@php
    // Build children-by-parent map server-side once.
    $byParent = [];
    foreach ($rows as $r) {
        $byParent[(string) ($r[$parent_col] ?? '__root__')][] = $r;
    }
    $ids = array_flip(array_map(fn ($r) => (string) $r[$pk_col], $rows));
    $roots = $byParent['__root__'] ?? [];
    foreach ($rows as $r) {
        $p = $r[$parent_col] ?? null;
        if ($p !== null && ! isset($ids[(string) $p])) $roots[] = $r;
    }
    $allIds = array_map(fn ($r) => (string) $r[$pk_col], $rows);
@endphp

@section('content')
<div x-data="dbLensTreeView({ ids: {{ Illuminate\Support\Js::from($allIds) }} })" class="space-y-3">
    {{-- Header --}}
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3 min-w-0">
            <span class="text-2xl">🌳</span>
            <div class="min-w-0">
                <div class="font-semibold text-slate-700">Tree view — <span class="mono">{{ $table }}</span></div>
                <div class="text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                    <span>Parent: <span class="mono text-slate-700">{{ $parent_col }}</span></span>
                    <span>Label: <span class="mono text-slate-700">{{ $label_col }}</span></span>
                    <span>{{ number_format(count($rows)) }} rows @if ($truncated) <span class="text-amber-600">(truncated)</span> @endif</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <input type="search" x-model="search" placeholder="🔍 Filter nodes…"
                   class="px-3 py-1.5 border border-slate-300 rounded text-sm w-48 focus:border-sky-400 focus:outline-none">
            <div class="inline-flex rounded border border-slate-200 overflow-hidden text-xs">
                <button type="button" @click="expandAll()" class="px-3 py-1.5 hover:bg-slate-100 border-r border-slate-200">Expand all</button>
                <button type="button" @click="collapseAll()" class="px-3 py-1.5 hover:bg-slate-100">Collapse all</button>
            </div>
            <form method="GET" class="flex items-center gap-1 text-xs">
                <label class="text-slate-500">Label:</label>
                <select name="label" onchange="this.form.submit()" class="px-2 py-1 border border-slate-300 rounded text-xs mono">
                    @foreach ($label_candidates as $c)
                        <option value="{{ $c }}" {{ $c === $label_col ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table]) }}"
               class="px-3 py-1.5 text-xs rounded bg-slate-100 hover:bg-slate-200">← Browse</a>
        </div>
    </div>

    {{-- Tree --}}
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-4 text-sm">
        @if (empty($roots))
            <div class="text-slate-400 italic text-center py-8">No root nodes.</div>
        @else
            <ul class="space-y-px">
                @foreach ($roots as $root)
                    @include('dblens::table.tree-node', ['node' => $root, 'byParent' => $byParent, 'pk_col' => $pk_col, 'parent_col' => $parent_col, 'label_col' => $label_col, 'depth' => 0])
                @endforeach
            </ul>
        @endif
    </div>
</div>

<script>
function dbLensTreeView(cfg) {
    return {
        ids: cfg.ids || [],
        open: {},
        search: '',
        childSearch: {},   // parent_id → search string for its direct children
        expandAll() {
            const map = {};
            for (const id of this.ids) map[id] = true;
            this.open = map;
        },
        collapseAll() { this.open = {}; this.childSearch = {}; },
        matches(label, id) {
            const q = (this.search || '').trim().toLowerCase();
            if (q === '') return true;
            return String(label || '').toLowerCase().includes(q) || String(id).toLowerCase().includes(q);
        },
        matchesChild(parentId, label, id) {
            const q = (this.childSearch[parentId] || '').trim().toLowerCase();
            if (q === '') return true;
            return String(label || '').toLowerCase().includes(q) || String(id).toLowerCase().includes(q);
        },
    };
}
</script>
@endsection
