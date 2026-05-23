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
@endphp

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200">
    <div class="px-4 py-3 border-b flex items-center justify-between gap-2 flex-wrap">
        <div>
            <div class="font-semibold">🌳 Tree view — <span class="mono">{{ $table }}</span></div>
            <div class="text-xs text-slate-500">
                Parent column: <span class="mono">{{ $parent_col }}</span>
                · Label: <span class="mono">{{ $label_col }}</span>
                · {{ number_format(count($rows)) }} rows
                @if ($truncated) <span class="text-amber-600">(truncated)</span> @endif
            </div>
        </div>
        <div class="flex items-center gap-2" x-data="{ open: {} }" x-init="window.__treeAlpine = $data">
            <button type="button" @click="$root.dispatchEvent(new CustomEvent('tree-expand-all'))" class="px-2 py-1 text-xs rounded bg-slate-100 hover:bg-slate-200">Expand all</button>
            <button type="button" @click="$root.dispatchEvent(new CustomEvent('tree-collapse-all'))" class="px-2 py-1 text-xs rounded bg-slate-100 hover:bg-slate-200">Collapse all</button>
            <form method="GET" class="flex items-center gap-1 text-xs">
                <label>Label:</label>
                <select name="label" onchange="this.form.submit()" class="px-2 py-1 border border-slate-300 rounded text-xs mono">
                    @foreach ($label_candidates as $c)
                        <option value="{{ $c }}" {{ $c === $label_col ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table]) }}"
               class="px-2 py-1 text-xs rounded bg-slate-100 hover:bg-slate-200">← Browse</a>
        </div>
    </div>

    <div class="p-4 text-sm mono"
         x-data="{ open: {}, expandAll() { @json(array_map(fn ($r) => (string) $r[$pk_col], $rows)).forEach(id => this.open[id] = true); }, collapseAll() { this.open = {}; } }"
         @tree-expand-all.window="expandAll()"
         @tree-collapse-all.window="collapseAll()">
        @if (empty($roots))
            <div class="text-slate-400 italic">No root nodes.</div>
        @else
            <ul class="space-y-0.5">
                @foreach ($roots as $root)
                    @include('dblens::table.tree-node', ['node' => $root, 'byParent' => $byParent, 'pk_col' => $pk_col, 'parent_col' => $parent_col, 'label_col' => $label_col, 'depth' => 0])
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
