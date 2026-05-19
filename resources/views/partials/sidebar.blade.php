<aside class="w-72 bg-slate-900 text-slate-100 flex flex-col sticky top-0 h-screen z-40" x-data="{ q: '' }">
    <div class="px-4 py-3 border-b border-slate-700 flex items-center justify-between">
        <a href="{{ route('dblens.database.show', ['connection' => $connection]) }}" class="font-bold text-lg">🔍 DbLens</a>
        <span class="text-xs text-slate-400 mono">{{ $connection }}</span>
    </div>

    <div class="px-3 py-2 border-b border-slate-700">
        <form action="{{ route('dblens.search', ['connection' => $connection]) }}" method="GET">
            <input type="text" name="q" placeholder="Search all tables…"
                   value="{{ request()->is('*search*') ? request('q') : '' }}"
                   class="w-full px-3 py-2 rounded bg-slate-800 border border-slate-700 text-sm focus:outline-none focus:border-sky-500">
        </form>
    </div>

    <div class="px-3 py-2 border-b border-slate-700">
        <input x-model="q" type="text" placeholder="Filter tables…" class="w-full px-3 py-2 rounded bg-slate-800 border border-slate-700 text-sm focus:outline-none focus:border-sky-500">
    </div>

    <nav class="flex-1 overflow-y-auto py-1 scroll-thin-dark">
        @foreach (($tables ?? []) as $t)
            <a x-show="q === '' || '{{ strtolower($t['name']) }}'.includes(q.toLowerCase())"
               href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $t['name']]) }}"
               class="block px-4 py-1.5 text-sm hover:bg-slate-800 {{ ($table ?? null) === $t['name'] ? 'bg-slate-800 border-l-2 border-sky-500' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="truncate mono">{{ $t['name'] }}</span>
                    <span class="text-xs text-slate-500">{{ number_format($t['rows']) }}</span>
                </div>
            </a>
        @endforeach
    </nav>

    <div class="px-3 py-2 border-t border-slate-700 text-xs">
        <a href="{{ route('dblens.sql.show', ['connection' => $connection]) }}" class="block px-2 py-1 rounded hover:bg-slate-800">⚡ SQL Editor</a>
        @if (count($connections ?? []) > 1)
            <details class="mt-2">
                <summary class="cursor-pointer px-2 py-1 text-slate-400">Connections</summary>
                @foreach ($connections as $c)
                    <a href="{{ route('dblens.database.show', ['connection' => $c]) }}"
                       class="block px-3 py-1 rounded text-slate-300 hover:bg-slate-800 {{ $c === $connection ? 'text-sky-400' : '' }}">{{ $c }}</a>
                @endforeach
            </details>
        @endif
    </div>
</aside>
