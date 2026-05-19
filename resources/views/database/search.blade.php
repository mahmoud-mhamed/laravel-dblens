@extends('dblens::layout')

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200 p-4 mb-4">
    <form method="GET" action="{{ route('dblens.search', ['connection' => $connection]) }}" class="flex gap-2">
        <input type="text" name="q" value="{{ $term }}" autofocus placeholder="Search term…" class="flex-1 px-3 py-2 border border-slate-300 rounded">
        <button class="px-4 py-2 bg-sky-600 text-white rounded hover:bg-sky-700">Search</button>
    </form>
    <p class="text-xs text-slate-500 mt-2">Searches all text/char columns across every table.</p>
</div>

@if ($term !== '')
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold">Results for <span class="mono">"{{ $term }}"</span></h2>
            <span class="text-xs text-slate-500">{{ count($results) }} table(s) matched</span>
        </div>
        @forelse ($results as $tableName => $r)
            <div class="border-t">
                <div class="px-4 py-2 bg-slate-50 flex items-center justify-between">
                    <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $tableName, 'search' => $term]) }}" class="mono text-sky-600 hover:underline">{{ $tableName }}</a>
                    <span class="text-xs text-slate-500">{{ number_format($r['matches']) }} match(es)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                @foreach (array_keys($r['sample'][0] ?? []) as $col)
                                    <th class="px-3 py-1 text-left mono">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($r['sample'] as $row)
                                <tr class="border-t">
                                    @foreach ($row as $val)
                                        <td class="px-3 py-1 mono truncate-cell">{{ is_null($val) ? '∅' : (string) $val }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-slate-400">No matches.</div>
        @endforelse
    </div>
@endif
@endsection
