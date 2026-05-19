@extends('dblens::layout')

@php
    $masked = array_map('strtolower', (array) config('dblens.masked_columns', []));
@endphp

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200 mb-4">
    <div class="px-4 py-3 border-b flex items-center justify-between">
        <div>
            <h1 class="font-semibold">Row · <span class="mono">{{ $table }}</span></h1>
            <span class="text-xs text-slate-500 mono">
                @foreach ($pk_values as $k => $v)
                    {{ $k }}={{ $v }}@if (!$loop->last) , @endif
                @endforeach
            </span>
        </div>
        @unless ($read_only ?? false)
            <div class="flex items-center gap-2">
                <a href="{{ route('dblens.row.edit', ['connection' => $connection, 'table' => $table, 'rowKey' => $row_key]) }}"
                   class="px-3 py-1.5 bg-amber-500 text-white rounded text-sm hover:bg-amber-600">Edit</a>
                <form method="POST" action="{{ route('dblens.row.destroy', ['connection' => $connection, 'table' => $table, 'rowKey' => $row_key]) }}"
                      onsubmit="return confirm('Delete this row? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirm" value="1">
                    <button class="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Delete</button>
                </form>
            </div>
        @endunless
    </div>
    <table class="w-full text-sm">
        <tbody>
        @foreach ($columns as $c)
            @php
                $val = $row[$c['name']] ?? null;
                $isMasked = in_array(strtolower($c['name']), $masked, true);
                $fk = $foreign_keys[$c['name']] ?? null;
            @endphp
            <tr class="border-t">
                <td class="px-4 py-2 w-1/4 text-slate-500">
                    <div class="mono">{{ $c['name'] }}</div>
                    <div class="text-xs text-slate-400">{{ $c['type'] }}</div>
                </td>
                <td class="px-4 py-2 mono">
                    @if ($val === null)
                        <span class="text-slate-400 italic">NULL</span>
                    @elseif ($isMasked)
                        <span class="text-slate-400">••••••</span>
                    @elseif ($fk)
                        @php $fkRowKey = rawurlencode(json_encode([$fk['foreign_column'] => $val])); @endphp
                        <a href="{{ route('dblens.row.show', ['connection' => $connection, 'table' => $fk['foreign_table'], 'rowKey' => $fkRowKey]) }}"
                           class="text-violet-600 hover:underline">
                            🔗 {{ $val }}
                        </a>
                        <span class="text-xs text-slate-400">→ {{ $fk['foreign_table'] }}.{{ $fk['foreign_column'] }}</span>
                    @else
                        @php
                            $strVal = (string) $val;
                            $isJsonType = stripos($c['type'], 'json') !== false;
                            $decoded = null;
                            $isJsonLike = false;
                            if ($isJsonType || (strlen($strVal) > 1 && ($strVal[0] === '{' || $strVal[0] === '['))) {
                                $decoded = json_decode($strVal, true);
                                $isJsonLike = json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded));
                            }
                        @endphp
                        @if ($isJsonLike)
                            <details open class="bg-slate-50 border border-slate-200 rounded">
                                <summary class="cursor-pointer px-2 py-1 text-xs text-slate-500 select-none flex items-center justify-between">
                                    <span>JSON</span>
                                    <span class="text-slate-400">{{ is_array($decoded) && array_is_list($decoded) ? count($decoded).' item(s)' : count((array) $decoded).' key(s)' }}</span>
                                </summary>
                                <pre class="px-3 py-2 text-xs overflow-x-auto whitespace-pre-wrap break-words mono leading-relaxed">{{ json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @else
                            <span class="whitespace-pre-wrap break-words">{{ $strVal }}</span>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if (! empty($incoming_fks))
    <div class="bg-white rounded shadow-sm border border-slate-200">
        <div class="px-4 py-3 border-b font-semibold">Related (incoming references)</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
                <tr>
                    <th class="px-4 py-2">From table</th>
                    <th class="px-4 py-2">Via column</th>
                    <th class="px-4 py-2 text-right">Rows</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($incoming_fks as $fk)
                @php
                    $foreignVal = $row[$fk['foreign_column']] ?? null;
                    $count = $incoming_counts[$fk['name']] ?? 0;
                @endphp
                <tr class="border-t">
                    <td class="px-4 py-2 mono">{{ $fk['table'] }}</td>
                    <td class="px-4 py-2 mono text-xs">{{ $fk['column'] }} → {{ $fk['foreign_column'] }}</td>
                    <td class="px-4 py-2 text-right mono">{{ number_format((int) $count) }}</td>
                    <td class="px-4 py-2 text-right">
                        @if ($foreignVal !== null && $count > 0)
                            <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $fk['table'], 'filters' => [['column' => $fk['column'], 'op' => '=', 'value' => $foreignVal]]]) }}"
                               class="text-xs text-sky-600 hover:underline">view related →</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
