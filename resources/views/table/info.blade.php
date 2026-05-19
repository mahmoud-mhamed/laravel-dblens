@extends('dblens::layout')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded shadow-sm border border-slate-200 p-4">
        <div class="text-xs text-slate-500">Rows (approx.)</div>
        <div class="text-2xl font-bold mono">{{ number_format($info['rows']) }}</div>
    </div>
    <div class="bg-white rounded shadow-sm border border-slate-200 p-4">
        <div class="text-xs text-slate-500">Total size</div>
        <div class="text-2xl font-bold mono">
            @if ($info['size'])
                @php $kb = $info['size'] / 1024; @endphp
                @if ($kb >= 1024) {{ number_format($kb / 1024, 2) }} MB @else {{ number_format($kb, 1) }} KB @endif
            @else — @endif
        </div>
    </div>
    <div class="bg-white rounded shadow-sm border border-slate-200 p-4">
        <div class="text-xs text-slate-500">Columns / Indexes / FKs</div>
        <div class="text-2xl font-bold mono">{{ count($columns) }} / {{ count($indexes) }} / {{ count($foreign_keys) }}</div>
    </div>
</div>

<div class="mt-4 bg-white rounded shadow-sm border border-slate-200">
    <div class="px-4 py-3 border-b font-semibold">Table info</div>
    <table class="w-full text-sm">
        <tbody>
            <tr class="border-t"><td class="px-4 py-2 w-1/3 text-slate-500">Engine</td><td class="px-4 py-2 mono">{{ $info['engine'] ?? '—' }}</td></tr>
            <tr class="border-t"><td class="px-4 py-2 text-slate-500">Collation</td><td class="px-4 py-2 mono">{{ $info['collation'] ?? '—' }}</td></tr>
            <tr class="border-t"><td class="px-4 py-2 text-slate-500">Auto increment</td><td class="px-4 py-2 mono">{{ $info['auto_increment'] ?? '—' }}</td></tr>
            <tr class="border-t"><td class="px-4 py-2 text-slate-500">Created</td><td class="px-4 py-2 mono">{{ $info['created_at'] ?? '—' }}</td></tr>
            <tr class="border-t"><td class="px-4 py-2 text-slate-500">Updated</td><td class="px-4 py-2 mono">{{ $info['updated_at'] ?? '—' }}</td></tr>
            <tr class="border-t"><td class="px-4 py-2 text-slate-500">Primary key</td><td class="px-4 py-2 mono">{{ $primary_key ? implode(', ', $primary_key) : '—' }}</td></tr>
            <tr class="border-t"><td class="px-4 py-2 text-slate-500">Comment</td><td class="px-4 py-2">{{ $info['comment'] ?: '—' }}</td></tr>
        </tbody>
    </table>
</div>
@endsection
