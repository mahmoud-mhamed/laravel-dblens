@extends('dblens::layout')

@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit
        ? route('dblens.row.update', ['connection' => $connection, 'table' => $table, 'rowKey' => $row_key])
        : route('dblens.row.store', ['connection' => $connection, 'table' => $table]);

    $inputClass = 'w-full px-3 py-2 border border-slate-300 rounded text-sm mono focus:outline-none focus:border-sky-500';
@endphp

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200">
    <div class="px-4 py-3 border-b flex items-center justify-between">
        <h1 class="font-semibold">
            {{ $isEdit ? 'Edit row' : 'Insert row' }} · <span class="mono">{{ $table }}</span>
        </h1>
        <a href="{{ $isEdit
            ? route('dblens.row.show', ['connection' => $connection, 'table' => $table, 'rowKey' => $row_key])
            : route('dblens.table.browse', ['connection' => $connection, 'table' => $table]) }}"
           class="text-sm text-slate-500 hover:underline">Cancel</a>
    </div>

    <form method="POST" action="{{ $action }}" class="divide-y">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        @foreach ($columns as $c)
            @php
                $name = $c['name'];
                $type = strtolower($c['type']);
                $old = old("row.{$name}", $row[$name] ?? ($isEdit ? null : $c['default']));
                $isAutoInc = stripos((string) ($c['extra'] ?? ''), 'auto_increment') !== false;
                $isPk = in_array($name, $primary_key, true);
                $fk = $foreign_keys[$name] ?? null;
                $phpEnumCases = ($enum_casts ?? [])[$name] ?? null;
                $isText = (bool) preg_match('/text|json/i', $type);
                $isBool = (bool) preg_match('/tinyint\(1\)|^bool/i', $type);
                $isDate = (bool) preg_match('/datetime|timestamp/i', $type);
                $isDateOnly = (bool) preg_match('/^date($|[^t])/i', $type);
                $isNumeric = (bool) preg_match('/int|decimal|numeric|float|double/i', $type);
                $enumValues = [];
                if (preg_match("/^enum\\((.*)\\)$/i", $type, $m)) {
                    foreach (explode(',', $m[1]) as $e) {
                        $enumValues[] = trim($e, " '\"");
                    }
                }
            @endphp
            <div class="px-4 py-3 grid grid-cols-12 gap-3 items-start">
                <label class="col-span-3 pt-2">
                    <div class="mono text-sm">{{ $name }}
                        @if ($isPk) <span class="text-amber-600" title="Primary key">🔑</span> @endif
                        @if ($fk) <span class="text-violet-600" title="FK">🔗</span> @endif
                    </div>
                    <div class="text-xs text-slate-400">{{ $c['type'] }} {{ $c['nullable'] ? '· nullable' : '' }}</div>
                    @if ($c['comment']) <div class="text-xs text-slate-400 italic">{{ $c['comment'] }}</div> @endif
                </label>
                <div class="col-span-9">
                    @if ($isAutoInc && ! $isEdit)
                        <input type="text" disabled placeholder="(auto)" class="{{ $inputClass }} bg-slate-50 text-slate-400">
                    @elseif ($phpEnumCases)
                        <select name="row[{{ $name }}]" class="{{ $inputClass }}">
                            @if ($c['nullable']) <option value="__NULL__" {{ $old === null ? 'selected' : '' }}>— NULL —</option> @endif
                            @foreach ($phpEnumCases as $case)
                                <option value="{{ $case['value'] }}" {{ (string) $old === (string) $case['value'] ? 'selected' : '' }}>
                                    {{ $case['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-violet-500 mt-0.5 mono">PHP enum cast detected</p>
                    @elseif (!empty($enumValues))
                        <select name="row[{{ $name }}]" class="{{ $inputClass }}">
                            @if ($c['nullable']) <option value="__NULL__" {{ $old === null ? 'selected' : '' }}>— NULL —</option> @endif
                            @foreach ($enumValues as $ev)
                                <option value="{{ $ev }}" {{ (string) $old === $ev ? 'selected' : '' }}>{{ $ev }}</option>
                            @endforeach
                        </select>
                    @elseif ($isBool)
                        <select name="row[{{ $name }}]" class="{{ $inputClass }}">
                            @if ($c['nullable']) <option value="__NULL__" {{ $old === null ? 'selected' : '' }}>— NULL —</option> @endif
                            <option value="0" {{ (string) $old === '0' ? 'selected' : '' }}>0 (false)</option>
                            <option value="1" {{ (string) $old === '1' ? 'selected' : '' }}>1 (true)</option>
                        </select>
                    @elseif ($isText)
                        <textarea name="row[{{ $name }}]" rows="4" class="{{ $inputClass }}">{{ $old }}</textarea>
                    @elseif ($isDate)
                        <input type="datetime-local" name="row[{{ $name }}]"
                               value="{{ $old ? \Illuminate\Support\Str::of((string) $old)->replaceMatches('/[ T]/', 'T')->before('.')->substr(0, 19) : '' }}"
                               class="{{ $inputClass }}">
                    @elseif ($isDateOnly)
                        <input type="date" name="row[{{ $name }}]" value="{{ $old }}" class="{{ $inputClass }}">
                    @else
                        <input type="{{ $isNumeric ? 'number' : 'text' }}"
                               @if ($isNumeric && preg_match('/decimal|float|double/i', $type)) step="any" @endif
                               name="row[{{ $name }}]" value="{{ $old }}" class="{{ $inputClass }}"
                               @if ($c['nullable']) placeholder="empty = NULL" @endif>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="px-4 py-3 bg-slate-50 flex items-center justify-end gap-2">
            <a href="{{ route('dblens.table.browse', ['connection' => $connection, 'table' => $table]) }}"
               class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</a>
            <button class="px-4 py-2 bg-sky-600 text-white rounded text-sm hover:bg-sky-700">
                {{ $isEdit ? 'Save changes' : 'Insert' }}
            </button>
        </div>
    </form>
</div>
@endsection
