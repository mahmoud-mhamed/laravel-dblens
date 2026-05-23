@extends('dblens::layout')

@php
    $masked = array_map('strtolower', (array) config('dblens.masked_columns', []));
@endphp

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200 mb-4"
     x-data="{ q: '', showSearch: false, match(col, val) { if (this.q === '') return true; const s = this.q.toLowerCase(); return col.toLowerCase().includes(s) || String(val ?? '').toLowerCase().includes(s); } }">
    <div class="px-4 py-3 border-b flex items-center justify-between gap-2">
        <div class="min-w-0">
            <h1 class="font-semibold">Row · <span class="mono">{{ $table }}</span></h1>
            <span class="text-xs text-slate-500 mono">
                @foreach ($pk_values as $k => $v)
                    {{ $k }}={{ $v }}@if (!$loop->last) , @endif
                @endforeach
            </span>
        </div>
        <div class="flex items-center gap-2">
            <input type="search" x-model="q"
                   placeholder="🔍 Filter columns/values…"
                   @keydown.escape="q = ''"
                   class="px-3 py-1.5 border border-slate-300 rounded text-sm mono w-56 focus:border-sky-400 focus:outline-none">
            @unless ($read_only ?? false)
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
            @endunless
        </div>
    </div>
    <table class="w-full text-sm">
        <tbody>
        @foreach ($columns as $c)
            @php
                $val = $row[$c['name']] ?? null;
                $isMasked = in_array(strtolower($c['name']), $masked, true);
                $fk = $foreign_keys[$c['name']] ?? null;
            @endphp
            <tr class="border-t" x-show="match(@js($c['name']), @js((string) ($val ?? '')))">
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
    <div class="space-y-3" x-data="{ relQ: '', relMatch(t, c) { if (this.relQ === '') return true; const s = this.relQ.toLowerCase(); return String(t).toLowerCase().includes(s) || String(c).toLowerCase().includes(s); } }">
        <div class="px-1 flex items-center justify-between gap-2">
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Related rows (incoming references)</h2>
            <input type="search" x-model="relQ"
                   placeholder="🔍 Filter table/column…"
                   @keydown.escape="relQ = ''"
                   class="px-3 py-1.5 border border-slate-300 rounded text-sm mono w-56 focus:border-sky-400 focus:outline-none">
        </div>

        @foreach ($incoming_fks as $fk)
            @php
                $foreignVal = $row[$fk['foreign_column']] ?? null;
                $count = $incoming_counts[$fk['name']] ?? 0;
                $preview = $incoming_previews[$fk['name']] ?? ['columns' => [], 'rows' => [], 'pk' => []];
                $browseUrl = route('dblens.table.browse', [
                    'connection' => $connection,
                    'table' => $fk['table'],
                    'filters' => [['column' => $fk['column'], 'op' => '=', 'value' => $foreignVal]],
                ]);
            @endphp
            <details x-show="relMatch(@js($fk['table']), @js($fk['column']))" class="bg-white rounded shadow-sm border border-slate-200 group" @if ($count > 0 && $count <= 10) open @endif>
                <summary class="cursor-pointer px-4 py-3 flex items-center justify-between hover:bg-slate-50">
                    <div class="min-w-0">
                        <span class="font-semibold mono">{{ $fk['table'] }}</span>
                        <span class="text-xs text-slate-500 mono ml-2">via {{ $fk['column'] }} → {{ $fk['foreign_column'] }}</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-sm mono {{ $count > 0 ? 'text-slate-700 font-semibold' : 'text-slate-400' }}">{{ number_format((int) $count) }} row(s)</span>
                        @if ($count > 0)
                            <a href="{{ $browseUrl }}" @click.stop class="text-xs text-sky-600 hover:underline">browse all →</a>
                        @endif
                    </div>
                </summary>

                @if ($count > 0 && ! empty($preview['rows']))
                    <div class="border-t overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600 text-left text-xs">
                                <tr>
                                    @foreach ($preview['columns'] as $c)
                                        <th class="px-3 py-2 mono">{{ $c }}</th>
                                    @endforeach
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview['rows'] as $r)
                                    @php
                                        // Build row-key for the child row's show page.
                                        $childPk = $preview['pk'];
                                        $childRk = null;
                                        if (! empty($childPk)) {
                                            $vals = [];
                                            foreach ($childPk as $c) $vals[$c] = $r[$c] ?? null;
                                            if (count($vals) === 1) {
                                                $childRk = rawurlencode((string) reset($vals));
                                            } else {
                                                $childRk = rawurlencode(json_encode($vals));
                                            }
                                        }
                                    @endphp
                                    <tr class="border-t hover:bg-slate-50">
                                        @foreach ($preview['columns'] as $c)
                                            @php $v = $r[$c] ?? null; @endphp
                                            <td class="px-3 py-1.5 mono text-xs truncate-cell">
                                                @if ($v === null)
                                                    <span class="text-slate-400 italic">NULL</span>
                                                @else
                                                    {{ \Illuminate\Support\Str::limit((string) $v, 80) }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                                            @if ($childRk !== null)
                                                <a href="{{ route('dblens.row.show', ['connection' => $connection, 'table' => $fk['table'], 'rowKey' => $childRk]) }}"
                                                   class="text-sky-600 hover:text-sky-800 text-xs" title="Open row">↗</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($count > count($preview['rows']))
                                    <tr class="border-t bg-slate-50">
                                        <td colspan="{{ count($preview['columns']) + 1 }}" class="px-3 py-2 text-center text-xs text-slate-500">
                                            Showing {{ count($preview['rows']) }} of {{ number_format($count) }} —
                                            <a href="{{ $browseUrl }}" class="text-sky-600 hover:underline">browse all →</a>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        @endforeach
    </div>
@endif
@endsection
