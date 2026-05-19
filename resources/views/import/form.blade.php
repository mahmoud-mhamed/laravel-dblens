@extends('dblens::layout')

@section('content')
@if ($table)
    <div class="bg-white rounded shadow-sm border border-slate-200 mb-4">
        <div class="px-4 py-3 border-b font-semibold">Import CSV into <span class="mono">{{ $table }}</span></div>
        <form method="POST" action="{{ route('dblens.table.import.csv', ['connection' => $connection, 'table' => $table]) }}" enctype="multipart/form-data" class="p-4 space-y-3">
            @csrf
            <input type="file" name="csv_file" accept=".csv,text/csv" required class="block">
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="has_header" value="1" checked> first row is header</label>
                <label class="flex items-center gap-2">delimiter <input type="text" name="delimiter" value="," maxlength="1" class="w-12 px-2 py-1 border border-slate-300 rounded text-center mono"></label>
            </div>
            <p class="text-xs text-slate-500">
                When the header is present, CSV columns matching table columns by name are imported (others ignored).
                Without a header, the first N CSV columns map to the first N table columns.
            </p>
            <button class="px-4 py-2 bg-sky-600 text-white rounded text-sm hover:bg-sky-700">Import CSV</button>
        </form>
    </div>
@endif

<div class="bg-white rounded shadow-sm border border-slate-200">
    <div class="px-4 py-3 border-b font-semibold">Import SQL dump into <span class="mono">{{ $database }}</span></div>
    <form method="POST" action="{{ route('dblens.database.import', ['connection' => $connection]) }}" enctype="multipart/form-data" class="p-4 space-y-3">
        @csrf
        <input type="file" name="sql_file" accept=".sql,text/plain" required class="block">
        <p class="text-xs text-slate-500">
            Runs all statements inside a transaction. Statements are split on <code class="mono">;</code> outside strings/comments.
            Heavy dumps with custom delimiters may not split correctly — use the SQL editor for those.
        </p>
        <button class="px-4 py-2 bg-sky-600 text-white rounded text-sm hover:bg-sky-700">Import SQL</button>
    </form>
</div>
@endsection
