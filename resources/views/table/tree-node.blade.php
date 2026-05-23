@php
    $id = $node[$pk_col] ?? null;
    $children = $byParent[(string) $id] ?? [];
    $hasChildren = count($children) > 0;
    $nodeKey = (string) $id;
    $rk = rawurlencode((string) $id);
    $softDeleted = array_key_exists('deleted_at', $node) && $node['deleted_at'] !== null;
@endphp
<li class="leading-snug">
    <div class="flex items-center gap-1 rounded px-1 py-0.5 {{ $softDeleted ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'hover:bg-slate-50' }}"
         @if ($softDeleted) title="Soft-deleted at {{ $node['deleted_at'] }}" @endif>
        @if ($hasChildren)
            <button type="button"
                    @click="open['{{ $nodeKey }}'] = ! open['{{ $nodeKey }}']"
                    class="text-slate-400 hover:text-slate-700 w-4 text-xs select-none">
                <span x-text="open['{{ $nodeKey }}'] ? '▾' : '▸'"></span>
            </button>
        @else
            <span class="w-4 text-slate-300 text-xs">·</span>
        @endif
        <span class="{{ $softDeleted ? 'text-red-700' : 'text-slate-800' }}">{{ $node[$label_col] ?? '(no label)' }}</span>
        <span class="{{ $softDeleted ? 'text-red-400' : 'text-slate-400' }} text-xs">#{{ $id }}</span>
        @if ($hasChildren)
            <span class="text-xs text-slate-400">({{ count($children) }})</span>
        @endif
        @if ($softDeleted)
            <span class="text-xs ml-1">🗑</span>
        @endif
        <a href="{{ route('dblens.row.show', ['connection' => $connection, 'table' => $table, 'rowKey' => $rk]) }}"
           class="ml-2 text-sky-600 hover:text-sky-800 text-xs" title="Open row page">🔍</a>
    </div>
    @if ($hasChildren)
        <ul x-show="open['{{ $nodeKey }}']" x-cloak class="ml-5 border-l border-slate-200 pl-2">
            @foreach ($children as $child)
                @include('dblens::table.tree-node', ['node' => $child, 'byParent' => $byParent, 'pk_col' => $pk_col, 'parent_col' => $parent_col, 'label_col' => $label_col, 'depth' => ($depth ?? 0) + 1])
            @endforeach
        </ul>
    @endif
</li>
