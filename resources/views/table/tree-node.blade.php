@php
    $id = $node[$pk_col] ?? null;
    $children = $byParent[(string) $id] ?? [];
    $hasChildren = count($children) > 0;
    $nodeKey = (string) $id;
    $rk = rawurlencode((string) $id);
    $softDeleted = array_key_exists('deleted_at', $node) && $node['deleted_at'] !== null;
    $label = (string) ($node[$label_col] ?? '(no label)');
    $parentId = $parentId ?? null;
@endphp
<li @if ($parentId !== null)
        x-show="matches(@js($label), @js((string) $id)) && matchesChild(@js((string) $parentId), @js($label), @js((string) $id))"
    @else
        x-show="matches(@js($label), @js((string) $id))"
    @endif>
    <div class="group flex items-center gap-1.5 rounded px-1.5 py-1 transition
                {{ $softDeleted ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'hover:bg-sky-50' }}
                {{ $hasChildren ? 'cursor-pointer' : '' }}"
         @if ($hasChildren) @click="open['{{ $nodeKey }}'] = ! open['{{ $nodeKey }}']" @endif
         @if ($softDeleted) title="Soft-deleted at {{ $node['deleted_at'] }}" @endif>
        @if ($hasChildren)
            <span class="w-5 h-5 inline-flex items-center justify-center rounded text-slate-400 select-none"
                  :title="open['{{ $nodeKey }}'] ? 'Collapse' : 'Expand'">
                <span x-text="open['{{ $nodeKey }}'] ? '▾' : '▸'" class="text-xs"></span>
            </span>
            <span class="text-base">📁</span>
        @else
            <span class="w-5 h-5 inline-block"></span>
            <span class="text-base opacity-60">📄</span>
        @endif

        <span class="truncate {{ $softDeleted ? 'text-red-700' : 'text-slate-800' }} font-medium">{{ $label }}</span>

        @if ($hasChildren)
            <input type="search"
                   x-show="open['{{ $nodeKey }}']"
                   x-cloak
                   x-model="childSearch['{{ $nodeKey }}']"
                   @click.stop
                   @keydown.escape.stop="childSearch['{{ $nodeKey }}'] = ''"
                   placeholder="🔍 children…"
                   class="px-1.5 py-0.5 border border-slate-200 rounded text-[10px] mono w-32 focus:border-sky-400 focus:outline-none ml-1">
        @endif

        <span class="flex-1"></span>

        @if ($hasChildren)
            <span class="relative" x-data="{ tip: false }">
                <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table, 'filters' => [['column' => $parent_col, 'op' => '=', 'value' => $id]]]) }}"
                   @click.stop
                   @mouseenter="tip = true" @mouseleave="tip = false"
                   class="text-violet-600 hover:text-violet-800 text-xs" title="Browse direct children">📂</a>
                <span x-show="tip" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-0.5 bg-violet-700 text-white text-[10px] rounded whitespace-nowrap z-30">Browse children in table</span>
            </span>
        @endif
        <span class="relative" x-data="{ tip: false }">
            <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table, 'filters' => [['column' => $pk_col, 'op' => '=', 'value' => $id]]]) }}"
               @click.stop
               @mouseenter="tip = true" @mouseleave="tip = false"
               class="text-emerald-600 hover:text-emerald-800 text-xs" title="Show this row in table view">📋</a>
            <span x-show="tip" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-0.5 bg-emerald-700 text-white text-[10px] rounded whitespace-nowrap z-30">Show this row in table</span>
        </span>
        <span class="relative" x-data="{ tip: false }">
            <a href="{{ route('dblens.row.show', ['connection' => $connection, 'table' => $table, 'rowKey' => $rk]) }}"
               @click.stop
               @mouseenter="tip = true" @mouseleave="tip = false"
               class="text-sky-600 hover:text-sky-800 text-xs" title="Open row page">↗</a>
            <span x-show="tip" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-0.5 bg-sky-700 text-white text-[10px] rounded whitespace-nowrap z-30">Open row page</span>
        </span>

        <span class="mono text-[11px] px-1.5 py-0.5 rounded
                     {{ $softDeleted ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-500' }}">#{{ $id }}</span>

        @if ($hasChildren)
            <span class="text-[10px] text-slate-500 mono px-1.5 py-0.5 bg-slate-100 rounded-full"
                  title="Number of direct children">
                {{ count($children) }} {{ count($children) === 1 ? 'child' : 'children' }}
            </span>
        @endif

        @if ($softDeleted)
            <span class="text-xs" title="Soft-deleted">🗑</span>
        @endif
    </div>

    @if ($hasChildren)
        <ul x-show="open['{{ $nodeKey }}']" x-cloak
            class="ml-3 pl-3 border-l border-dashed border-slate-300 mt-px space-y-px">
            @foreach ($children as $child)
                @include('dblens::table.tree-node', ['node' => $child, 'byParent' => $byParent, 'pk_col' => $pk_col, 'parent_col' => $parent_col, 'label_col' => $label_col, 'depth' => ($depth ?? 0) + 1, 'parentId' => $id])
            @endforeach
        </ul>
    @endif
</li>
