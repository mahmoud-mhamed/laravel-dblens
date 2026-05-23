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
                      data-confirm-title="Delete row"
                      data-confirm="Delete this row from [{{ $table }}]? This cannot be undone."
                      data-confirm-text="Delete">
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
                            $trim = ltrim($strVal);
                            $isJsonType = stripos($c['type'], 'json') !== false;

                            $kind = 'text';
                            $decoded = null;

                            if ($isJsonType || (strlen($trim) > 1 && ($trim[0] === '{' || $trim[0] === '['))) {
                                $decoded = json_decode($strVal, true);
                                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) $kind = 'json';
                            }
                            if ($kind === 'text' && preg_match('/^https?:\/\/\S+\.(png|jpe?g|gif|webp|svg|bmp|ico)(\?\S*)?$/i', trim($strVal))) {
                                $kind = 'image_url';
                            }
                            if ($kind === 'text' && preg_match('/^data:image\/(png|jpe?g|gif|webp|svg\+xml|bmp);base64,/i', $trim)) {
                                $kind = 'image_data';
                            }
                            if ($kind === 'text' && preg_match('/^https?:\/\/\S+$/i', trim($strVal)) && strlen(trim($strVal)) < 2048) {
                                $kind = 'url';
                            }
                            if ($kind === 'text' && strlen($trim) > 0 && $trim[0] === '<' && preg_match('/^<\?xml|^<[a-zA-Z][^>]*>.*<\/[a-zA-Z][^>]*>\s*$/s', $trim)) {
                                $kind = 'xml';
                            }
                            if ($kind === 'text' && preg_match('/(^#{1,6}\s|\*\*[^*]+\*\*|\[[^\]]+\]\([^)]+\)|^```|^- )/m', $strVal) && strlen($strVal) < 50000) {
                                $kind = 'markdown';
                            }
                        @endphp
                        @switch($kind)
                            @case('json')
                                <details open class="bg-slate-50 border border-slate-200 rounded">
                                    <summary class="cursor-pointer px-2 py-1 text-xs text-slate-500 select-none flex items-center justify-between">
                                        <span>JSON</span>
                                        <span class="text-slate-400">{{ is_array($decoded) && array_is_list($decoded) ? count($decoded).' item(s)' : count((array) $decoded).' key(s)' }}</span>
                                    </summary>
                                    <pre class="px-3 py-2 text-xs overflow-x-auto whitespace-pre-wrap break-words mono leading-relaxed">{{ json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                                @break
                            @case('image_url')
                            @case('image_data')
                                <details open class="bg-slate-50 border border-slate-200 rounded">
                                    <summary class="cursor-pointer px-2 py-1 text-xs text-slate-500 select-none">Image</summary>
                                    <div class="p-2 flex flex-col gap-2">
                                        <img src="{{ $strVal }}" alt="" class="max-h-64 max-w-full border border-slate-200 rounded bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><rect width=%228%22 height=%228%22 fill=%22%23eee%22/><rect x=%228%22 y=%228%22 width=%228%22 height=%228%22 fill=%22%23eee%22/></svg>')]" loading="lazy">
                                        <code class="text-xs text-slate-500 break-all">{{ \Illuminate\Support\Str::limit($strVal, 200) }}</code>
                                    </div>
                                </details>
                                @break
                            @case('url')
                                <a href="{{ $strVal }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline break-all">{{ $strVal }}</a>
                                <span class="text-xs text-slate-400">↗</span>
                                @break
                            @case('xml')
                                <details open class="bg-slate-50 border border-slate-200 rounded">
                                    <summary class="cursor-pointer px-2 py-1 text-xs text-slate-500 select-none">XML</summary>
                                    <pre class="px-3 py-2 text-xs overflow-x-auto whitespace-pre-wrap break-words mono leading-relaxed">{{ $strVal }}</pre>
                                </details>
                                @break
                            @case('markdown')
                                <details class="bg-slate-50 border border-slate-200 rounded" x-data="{ raw: false }">
                                    <summary class="cursor-pointer px-2 py-1 text-xs text-slate-500 select-none flex justify-between">
                                        <span>Markdown</span>
                                        <button type="button" @click.prevent="raw = !raw" class="text-slate-400 hover:text-slate-600" x-text="raw ? 'rendered' : 'raw'"></button>
                                    </summary>
                                    <div x-show="!raw" class="px-3 py-2 prose prose-sm max-w-none" x-html="window.dbLensMd ? window.dbLensMd(@js($strVal)) : @js(nl2br(e($strVal)))"></div>
                                    <pre x-show="raw" x-cloak class="px-3 py-2 text-xs overflow-x-auto whitespace-pre-wrap break-words mono leading-relaxed">{{ $strVal }}</pre>
                                </details>
                                @break
                            @default
                                <span class="whitespace-pre-wrap break-words">{{ $strVal }}</span>
                        @endswitch
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
