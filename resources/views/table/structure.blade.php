@extends('dblens::layout')

@php
    $columnNames = array_map(fn ($c) => $c['name'], $columns);
    $inputC = 'px-2 py-1 border border-slate-300 rounded text-xs mono';
@endphp

@section('content')
@unless ($read_only)
    <div class="bg-white rounded shadow-sm border border-slate-200 mb-4 p-3 flex flex-wrap gap-2">
        <details>
            <summary class="cursor-pointer text-sm px-3 py-1 bg-slate-100 rounded hover:bg-slate-200">Rename table</summary>
            <form method="POST" action="{{ route('dblens.table.rename', ['connection' => $connection, 'table' => $table]) }}" class="mt-2 flex gap-2">
                @csrf
                <input type="text" name="to" placeholder="new_name" class="{{ $inputC }}" required>
                <button class="px-3 py-1 bg-sky-600 text-white rounded text-xs">Rename</button>
            </form>
        </details>

        @if (config('dblens.allow_truncate', true))
            <form method="POST" action="{{ route('dblens.table.truncate', ['connection' => $connection, 'table' => $table]) }}"
                  data-confirm-title="Truncate table"
                  data-confirm="TRUNCATE TABLE will remove ALL rows from [{{ $table }}]. This cannot be undone."
                  data-confirm-text="Truncate"
                  data-confirm-type="{{ $table }}">
                @csrf
                <input type="hidden" name="confirm" value="1">
                <button class="px-3 py-1 bg-amber-500 text-white rounded text-xs">Truncate</button>
            </form>
        @endif

        @if (config('dblens.allow_drop_table', true))
            <form method="POST" action="{{ route('dblens.table.drop', ['connection' => $connection, 'table' => $table]) }}"
                  data-confirm-title="Drop table"
                  data-confirm="DROP TABLE [{{ $table }}] permanently. The table structure and all data will be lost. This cannot be undone."
                  data-confirm-text="Drop table"
                  data-confirm-type="{{ $table }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="confirm" value="1">
                <button class="px-3 py-1 bg-red-600 text-white rounded text-xs">Drop table</button>
            </form>
        @endif
    </div>
@endunless

{{-- ─── Columns ───────────────────────────────────────────────── --}}
<div class="bg-white rounded shadow-sm border border-slate-200 mb-4" x-data="{ q: '', matches(hay) { return this.q === '' || hay.toLowerCase().includes(this.q.toLowerCase()); } }">
    <div class="px-4 py-3 border-b font-semibold flex items-center justify-between gap-2">
        <span>Columns</span>
        <input type="search" x-model="q" placeholder="Search columns…" class="{{ $inputC }} w-64">
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-left">
            <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Type</th>
                <th class="px-4 py-2">Null</th>
                <th class="px-4 py-2">Default</th>
                <th class="px-4 py-2">Key</th>
                <th class="px-4 py-2">Extra</th>
                @unless ($read_only) <th class="px-4 py-2"></th> @endunless
            </tr>
        </thead>
        <tbody>
        @foreach ($columns as $i => $c)
            <tr class="border-t" x-data="{ editing: false, renaming: false }" x-show="matches(@js($c['name'].' '.$c['type'].' '.($c['comment'] ?? '').' '.($c['key'] ?? '').' '.($c['extra'] ?? '')))">
                <td class="px-4 py-2 text-slate-400 mono">{{ $i + 1 }}</td>
                <td class="px-4 py-2 mono">
                    <span x-show="!renaming">
                        {{ $c['name'] }}
                        @if (in_array($c['name'], $primary_key, true)) <span class="text-amber-600">🔑</span> @endif
                    </span>
                    @unless ($read_only)
                        <form x-show="renaming" x-cloak method="POST" action="{{ route('dblens.column.rename', ['connection' => $connection, 'table' => $table, 'column' => $c['name']]) }}" class="flex gap-1">
                            @csrf
                            <input type="text" name="to" value="{{ $c['name'] }}" class="{{ $inputC }}" required>
                            <button class="px-2 py-1 bg-sky-600 text-white rounded text-xs">✓</button>
                            <button type="button" @click="renaming = false" class="px-2 py-1 text-slate-500 text-xs">✕</button>
                        </form>
                    @endunless
                </td>
                <td class="px-4 py-2 mono text-slate-600">{{ $c['type'] }}</td>
                <td class="px-4 py-2 text-xs">{{ $c['nullable'] ? 'YES' : 'NO' }}</td>
                <td class="px-4 py-2 mono text-xs text-slate-500">{{ $c['default'] ?? '—' }}</td>
                <td class="px-4 py-2 text-xs">{{ $c['key'] ?? '' }}</td>
                <td class="px-4 py-2 text-xs text-slate-500">{{ $c['extra'] ?? '' }}</td>
                @unless ($read_only)
                    <td class="px-4 py-2 text-right text-xs whitespace-nowrap">
                        <button type="button" @click="editing = !editing" class="text-sky-600 hover:underline">modify</button>
                        <span class="text-slate-300 mx-1">·</span>
                        <button type="button" @click="renaming = !renaming" class="text-slate-600 hover:underline">rename</button>
                        <span class="text-slate-300 mx-1">·</span>
                        <form method="POST" action="{{ route('dblens.column.drop', ['connection' => $connection, 'table' => $table, 'column' => $c['name']]) }}" class="inline"
                              data-confirm-title="Drop column"
                              data-confirm="Drop column [{{ $c['name'] }}] from [{{ $table }}]? All data in this column will be lost. This cannot be undone."
                              data-confirm-text="Drop column">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="confirm" value="1">
                            <button class="text-red-600 hover:underline">drop</button>
                        </form>
                    </td>
                @endunless
            </tr>
            @unless ($read_only)
                <tr x-show="editing" x-cloak class="border-t bg-slate-50">
                    <td colspan="8" class="px-4 py-3">
                        <form method="POST" action="{{ route('dblens.column.modify', ['connection' => $connection, 'table' => $table, 'column' => $c['name']]) }}"
                              class="grid grid-cols-12 gap-2 text-xs">
                            @csrf
                            @method('PUT')
                            <input class="{{ $inputC }} col-span-3" name="type" value="{{ $c['type'] }}" placeholder="VARCHAR(255)" required>
                            <input class="{{ $inputC }} col-span-2" name="default" value="{{ $c['default'] }}" placeholder="default">
                            <label class="col-span-2 flex items-center gap-1"><input type="checkbox" name="nullable" value="1" {{ $c['nullable'] ? 'checked' : '' }}> nullable</label>
                            <input class="{{ $inputC }} col-span-3" name="comment" value="{{ $c['comment'] }}" placeholder="comment">
                            <button class="col-span-2 px-3 py-1 bg-sky-600 text-white rounded">Save</button>
                        </form>
                    </td>
                </tr>
            @endunless
        @endforeach
        </tbody>
    </table>

    @unless ($read_only)
        <details class="border-t bg-slate-50">
            <summary class="cursor-pointer px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">+ Add column</summary>
            <form method="POST" action="{{ route('dblens.column.add', ['connection' => $connection, 'table' => $table]) }}" class="p-3 grid grid-cols-12 gap-2 text-xs">
                @csrf
                <input class="{{ $inputC }} col-span-2" name="name" placeholder="column_name" required>
                <input class="{{ $inputC }} col-span-3" name="type" placeholder="VARCHAR(255)" required>
                <input class="{{ $inputC }} col-span-2" name="default" placeholder="default (optional)">
                <label class="col-span-1 flex items-center gap-1"><input type="checkbox" name="nullable" value="1"> null</label>
                <select class="{{ $inputC }} col-span-2" name="after">
                    <option value="">— end —</option>
                    @foreach ($columnNames as $cn)
                        <option value="{{ $cn }}">after {{ $cn }}</option>
                    @endforeach
                </select>
                <button class="col-span-2 px-3 py-1 bg-emerald-600 text-white rounded">Add</button>
            </form>
        </details>
    @endunless
</div>

{{-- ─── Indexes ──────────────────────────────────────────────── --}}
<div class="bg-white rounded shadow-sm border border-slate-200 mb-4">
    <div class="px-4 py-3 border-b font-semibold">Indexes</div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-left">
            <tr>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Columns</th>
                <th class="px-4 py-2">Unique</th>
                <th class="px-4 py-2">Primary</th>
                @unless ($read_only) <th class="px-4 py-2"></th> @endunless
            </tr>
        </thead>
        <tbody>
        @forelse ($indexes as $idx)
            <tr class="border-t">
                <td class="px-4 py-2 mono">{{ $idx['name'] }}</td>
                <td class="px-4 py-2 mono">{{ implode(', ', $idx['columns']) }}</td>
                <td class="px-4 py-2 text-xs">{{ $idx['unique'] ? '✓' : '' }}</td>
                <td class="px-4 py-2 text-xs">{{ $idx['primary'] ? '✓' : '' }}</td>
                @unless ($read_only)
                    <td class="px-4 py-2 text-right">
                        @unless ($idx['primary'])
                            <form method="POST" action="{{ route('dblens.index.drop', ['connection' => $connection, 'table' => $table, 'index' => $idx['name']]) }}"
                                  data-confirm-title="Drop index"
                                  data-confirm="Drop index [{{ $idx['name'] }}] from [{{ $table }}]? This cannot be undone."
                                  data-confirm-text="Drop index">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="confirm" value="1">
                                <button class="text-xs text-red-600 hover:underline">drop</button>
                            </form>
                        @endunless
                    </td>
                @endunless
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-4 text-center text-slate-400">No indexes.</td></tr>
        @endforelse
        </tbody>
    </table>
    @unless ($read_only)
        <details class="border-t bg-slate-50">
            <summary class="cursor-pointer px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">+ Add index</summary>
            <form method="POST" action="{{ route('dblens.index.add', ['connection' => $connection, 'table' => $table]) }}" class="p-3 grid grid-cols-12 gap-2 text-xs">
                @csrf
                <input class="{{ $inputC }} col-span-3" name="name" placeholder="idx_name" required>
                <select class="{{ $inputC }} col-span-5" name="columns[]" multiple required size="3">
                    @foreach ($columnNames as $cn) <option value="{{ $cn }}">{{ $cn }}</option> @endforeach
                </select>
                <label class="col-span-2 flex items-center gap-1"><input type="checkbox" name="unique" value="1"> unique</label>
                <button class="col-span-2 px-3 py-1 bg-emerald-600 text-white rounded">Add</button>
            </form>
        </details>
    @endunless
</div>

{{-- ─── Foreign keys (outgoing) ──────────────────────────────── --}}
<div class="bg-white rounded shadow-sm border border-slate-200 mb-4">
    <div class="px-4 py-3 border-b font-semibold">Foreign keys (outgoing)</div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-left">
            <tr>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Column</th>
                <th class="px-4 py-2">References</th>
                <th class="px-4 py-2">On Update</th>
                <th class="px-4 py-2">On Delete</th>
                @unless ($read_only) <th class="px-4 py-2"></th> @endunless
            </tr>
        </thead>
        <tbody>
        @forelse ($foreign_keys as $fk)
            <tr class="border-t">
                <td class="px-4 py-2 mono">{{ $fk['name'] }}</td>
                <td class="px-4 py-2 mono">{{ $fk['column'] }}</td>
                <td class="px-4 py-2 mono">
                    <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $fk['foreign_table']]) }}" class="text-sky-600 hover:underline">{{ $fk['foreign_table'] }}.{{ $fk['foreign_column'] }}</a>
                </td>
                <td class="px-4 py-2 text-xs">{{ $fk['on_update'] ?? '' }}</td>
                <td class="px-4 py-2 text-xs">{{ $fk['on_delete'] ?? '' }}</td>
                @unless ($read_only)
                    <td class="px-4 py-2 text-right">
                        <form method="POST" action="{{ route('dblens.table.fk.drop', ['connection' => $connection, 'table' => $table, 'fk' => $fk['name']]) }}"
                              data-confirm-title="Drop foreign key"
                              data-confirm="Drop foreign key [{{ $fk['name'] }}] from [{{ $table }}]? The referential integrity constraint will be removed. This cannot be undone."
                              data-confirm-text="Drop FK">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="confirm" value="1">
                            <button class="text-xs text-red-600 hover:underline">drop</button>
                        </form>
                    </td>
                @endunless
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-4 text-center text-slate-400">No outgoing foreign keys.</td></tr>
        @endforelse
        </tbody>
    </table>
    @unless ($read_only)
        <details class="border-t bg-slate-50">
            <summary class="cursor-pointer px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">+ Add foreign key</summary>
            <form method="POST" action="{{ route('dblens.fk.add', ['connection' => $connection, 'table' => $table]) }}" class="p-3 grid grid-cols-12 gap-2 text-xs">
                @csrf
                <input class="{{ $inputC }} col-span-3" name="name" placeholder="fk_name" required>
                <select class="{{ $inputC }} col-span-2" name="column" required>
                    <option value="">column</option>
                    @foreach ($columnNames as $cn) <option value="{{ $cn }}">{{ $cn }}</option> @endforeach
                </select>
                <select class="{{ $inputC }} col-span-2" name="foreign_table" required>
                    <option value="">→ table</option>
                    @foreach ($tables as $t) <option value="{{ $t['name'] }}">{{ $t['name'] }}</option> @endforeach
                </select>
                <input class="{{ $inputC }} col-span-1" name="foreign_column" placeholder="col" required>
                <select class="{{ $inputC }} col-span-1" name="on_update">
                    <option value="">on update</option>
                    @foreach (['CASCADE','SET NULL','RESTRICT','NO ACTION'] as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach
                </select>
                <select class="{{ $inputC }} col-span-1" name="on_delete">
                    <option value="">on delete</option>
                    @foreach (['CASCADE','SET NULL','RESTRICT','NO ACTION'] as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach
                </select>
                <button class="col-span-2 px-3 py-1 bg-emerald-600 text-white rounded">Add</button>
            </form>
        </details>
    @endunless
</div>

{{-- ─── Incoming FKs ─────────────────────────────────────────── --}}
<div class="bg-white rounded shadow-sm border border-slate-200">
    <div class="px-4 py-3 border-b font-semibold">Referenced by (incoming)</div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-left">
            <tr>
                <th class="px-4 py-2">FK Name</th>
                <th class="px-4 py-2">From Table</th>
                <th class="px-4 py-2">Column</th>
                <th class="px-4 py-2">→ This.{{ $primary_key ? implode(',', $primary_key) : '' }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($incoming_fks as $fk)
            <tr class="border-t">
                <td class="px-4 py-2 mono">{{ $fk['name'] }}</td>
                <td class="px-4 py-2 mono">
                    <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $fk['table']]) }}" class="text-sky-600 hover:underline">{{ $fk['table'] }}</a>
                </td>
                <td class="px-4 py-2 mono">{{ $fk['column'] }}</td>
                <td class="px-4 py-2 mono text-xs text-slate-500">{{ $fk['foreign_column'] }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-4 text-center text-slate-400">Nothing references this table.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
