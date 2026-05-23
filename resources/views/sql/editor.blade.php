@extends('dblens::layout')

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200 mb-4">
    <form method="POST" action="{{ route('dblens.sql.run', ['connection' => $connection]) }}">
        @csrf
        <textarea name="sql" rows="8" autofocus
                  class="w-full p-3 mono text-sm focus:outline-none"
                  placeholder="SELECT * FROM users LIMIT 10;">{{ old('sql', $sql) }}</textarea>
        <div class="px-3 py-2 bg-slate-50 border-t flex items-center justify-between">
            <span class="text-xs text-slate-500">
                {{ config('dblens.sql_editor.allow_writes') && ! config('dblens.read_only') ? '✏️ Writes ENABLED' : '🔒 Read-only' }}
                · max {{ config('dblens.sql_editor.max_rows', 1000) }} rows
            </span>
            <div class="flex gap-2">
                <button type="submit" name="action" value="explain"
                        class="px-3 py-1.5 bg-slate-200 text-slate-700 rounded text-sm hover:bg-slate-300"
                        title="Show query plan without executing">EXPLAIN</button>
                <button type="submit" name="action" value="run"
                        class="px-4 py-1.5 bg-sky-600 text-white rounded text-sm hover:bg-sky-700">Run</button>
            </div>
        </div>
    </form>
</div>

@if ($error)
    <div class="bg-red-50 border border-red-200 text-red-800 rounded p-3 mono text-sm">{{ $error }}</div>
@endif

@if ($result)
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-2 border-b bg-slate-50 text-xs text-slate-500 flex justify-between">
            <span>
                @if ($result['type'] === 'read')
                    {{ count($result['rows']) }} row(s){{ $result['truncated'] ? ' (truncated)' : '' }}
                @else
                    {{ $result['affected'] }} affected
                @endif
            </span>
            <span>{{ $result['duration_ms'] }} ms</span>
        </div>
        @if ($result['type'] === 'read' && ! empty($result['rows']))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-left">
                        <tr>
                            @foreach ($result['columns'] as $col)
                                <th class="px-3 py-2 mono">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $r)
                            <tr class="border-t">
                                @foreach ($result['columns'] as $col)
                                    <td class="px-3 py-1 mono truncate-cell">
                                        @php $v = $r[$col] ?? null; @endphp
                                        @if ($v === null)
                                            <span class="text-slate-400 italic">NULL</span>
                                        @else
                                            {{ (string) $v }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($result['type'] === 'read')
            <div class="p-4 text-center text-slate-400 text-sm">No rows.</div>
        @endif
    </div>
@endif
@endsection
