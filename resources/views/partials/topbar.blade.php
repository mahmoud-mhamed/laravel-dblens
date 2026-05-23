<div class="bg-white border-b border-slate-200 px-4 py-2 flex items-center justify-between">
    <div class="text-sm flex items-center gap-2 min-w-0">
        @php $tenant = app(\MahmoudMhamed\DbLens\Services\TenancyManager::class)->current(); @endphp
        @if ($tenant)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-800 rounded-full text-xs mono"
                  title="Active tenant via stancl/tenancy{{ $tenant['domain'] ? ' · '.$tenant['domain'] : '' }}">
                🏢 <span>{{ $tenant['name'] ?? $tenant['id'] }}</span>
                <span class="text-violet-400 text-[10px]">#{{ $tenant['id'] }}</span>
            </span>
            <span class="text-slate-300">·</span>
        @endif
        <a href="{{ route('dblens.database.show', ['connection' => $connection]) }}" class="text-sky-600 hover:underline">{{ $database }}</a>
        @isset($table)
            <span class="text-slate-400 mx-1">/</span>
            <span class="mono font-semibold">{{ $table }}</span>
            <span class="ml-3 text-xs">
                <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table]) }}"
                   class="px-2 py-1 rounded {{ request()->routeIs('dblens.table.browse') ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100' }}">Browse</a>
                <a href="{{ route('dblens.table.structure', ['connection' => $connection, 'table' => $table]) }}"
                   class="px-2 py-1 rounded {{ request()->routeIs('dblens.table.structure') ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100' }}">Structure</a>
                <a href="{{ route('dblens.table.info', ['connection' => $connection, 'table' => $table]) }}"
                   class="px-2 py-1 rounded {{ request()->routeIs('dblens.table.info') ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100' }}">Info</a>
                @if (config('dblens.allow_export', true))
                    <span class="text-slate-300 mx-1">|</span>
                    <span x-data="{ open: false }" class="relative inline-block">
                        <button @click="open = !open" type="button" class="px-2 py-1 rounded text-slate-600 hover:bg-slate-100">⬇ Export</button>
                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 w-32 bg-white border border-slate-200 rounded shadow-lg z-10 text-left">
                            <a href="{{ route('dblens.table.export', ['connection' => $connection, 'table' => $table, 'format' => 'sql']) }}" class="block px-3 py-1.5 hover:bg-slate-50">SQL</a>
                            <a href="{{ route('dblens.table.export', ['connection' => $connection, 'table' => $table, 'format' => 'csv']) }}" class="block px-3 py-1.5 hover:bg-slate-50">CSV</a>
                            <a href="{{ route('dblens.table.export', ['connection' => $connection, 'table' => $table, 'format' => 'json']) }}" class="block px-3 py-1.5 hover:bg-slate-50">JSON</a>
                        </div>
                    </span>
                @endif
                @if (! config('dblens.read_only') && config('dblens.allow_import', true))
                    <a href="{{ route('dblens.table.import.form', ['connection' => $connection, 'table' => $table]) }}"
                       class="px-2 py-1 rounded text-slate-600 hover:bg-slate-100">⬆ Import</a>
                @endif
                <span class="text-slate-300 mx-1">|</span>
                @stack('topbar-actions')
            </span>
        @endisset
    </div>
    <div class="text-xs text-slate-500 flex items-center gap-2">
        <div id="dblens-topbar-extra" class="flex items-center gap-2"></div>
        @if (config('dblens.read_only'))
            <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded">READ-ONLY</span>
        @endif
        @if (config('dblens.viewer.password'))
            <form method="POST" action="{{ route('dblens.logout') }}" class="inline">
                @csrf
                <button type="submit" class="px-2 py-1 rounded text-slate-600 hover:bg-slate-100">Logout</button>
            </form>
        @endif
    </div>
</div>
