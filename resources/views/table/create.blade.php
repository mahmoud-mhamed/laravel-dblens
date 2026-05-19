@extends('dblens::layout')

@php $inputC = 'w-full px-2 py-1 border border-slate-300 rounded text-sm mono'; @endphp

@section('content')
<div class="bg-white rounded shadow-sm border border-slate-200">
    <div class="px-4 py-3 border-b font-semibold">Create table</div>
    @php
        $typeGroups = [
            'Integer' => ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT','TINYINT UNSIGNED','SMALLINT UNSIGNED','MEDIUMINT UNSIGNED','INT UNSIGNED','BIGINT UNSIGNED'],
            'Decimal / Float' => ['DECIMAL(10,2)','DECIMAL(8,2)','DECIMAL(15,4)','FLOAT','DOUBLE'],
            'String' => ['VARCHAR(255)','VARCHAR(191)','VARCHAR(100)','VARCHAR(50)','CHAR(36)','CHAR(2)','TEXT','MEDIUMTEXT','LONGTEXT','TINYTEXT'],
            'Date / Time' => ['DATE','DATETIME','TIMESTAMP','TIME','YEAR'],
            'Boolean / JSON / Binary' => ['BOOLEAN','TINYINT(1)','JSON','BLOB','LONGBLOB'],
        ];
    @endphp
    <form method="POST" action="{{ route('dblens.table.create', ['connection' => $connection]) }}" x-data="{
        cols: [
            { name: 'id', type: 'BIGINT UNSIGNED', customType: '', nullable: false, default: '', primary: true, auto_increment: true },
            { name: 'created_at', type: 'TIMESTAMP', customType: '', nullable: true, default: 'CURRENT_TIMESTAMP', primary: false, auto_increment: false },
        ],
        add() { this.cols.push({ name: '', type: 'VARCHAR(255)', customType: '', nullable: false, default: '', primary: false, auto_increment: false }); },
        remove(i) { this.cols.splice(i, 1); },
        effectiveType(c) { return c.type === '__custom__' ? c.customType : c.type; }
    }">
        @csrf
        <div class="p-4 space-y-3">
            <div>
                <label class="text-sm text-slate-600">Table name</label>
                <input name="name" required value="{{ old('name') }}" class="{{ $inputC }}" placeholder="table_name">
            </div>

            <div>
                <div class="text-sm text-slate-600 mb-1">Columns</div>
                <table class="w-full text-xs">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="text-left px-2 py-1">Name</th>
                            <th class="text-left px-2 py-1">Type</th>
                            <th class="text-left px-2 py-1">Default</th>
                            <th class="text-left px-2 py-1">Null</th>
                            <th class="text-left px-2 py-1">PK</th>
                            <th class="text-left px-2 py-1">A_I</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(c, i) in cols" :key="i">
                            <tr>
                                <td class="px-2 py-1"><input :name="`columns[${i}][name]`" x-model="c.name" class="{{ $inputC }}" placeholder="column"></td>
                                <td class="px-2 py-1">
                                    <div class="flex flex-col gap-1">
                                        <select x-model="c.type" class="{{ $inputC }}">
                                            @foreach ($typeGroups as $group => $types)
                                                <optgroup label="{{ $group }}">
                                                    @foreach ($types as $t)
                                                        <option value="{{ $t }}">{{ $t }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                            <option value="__custom__">— Custom… —</option>
                                        </select>
                                        <input x-show="c.type === '__custom__'" x-cloak
                                               x-model="c.customType"
                                               class="{{ $inputC }}" placeholder="e.g. ENUM('a','b'), POINT, INET">
                                        <input type="hidden" :name="`columns[${i}][type]`" :value="effectiveType(c)">
                                    </div>
                                </td>
                                <td class="px-2 py-1"><input :name="`columns[${i}][default]`" x-model="c.default" class="{{ $inputC }}" placeholder="default"></td>
                                <td class="px-2 py-1"><input type="checkbox" :name="`columns[${i}][nullable]`" value="1" x-model="c.nullable"></td>
                                <td class="px-2 py-1"><input type="checkbox" :name="`columns[${i}][primary]`" value="1" x-model="c.primary"></td>
                                <td class="px-2 py-1"><input type="checkbox" :name="`columns[${i}][auto_increment]`" value="1" x-model="c.auto_increment"></td>
                                <td class="px-2 py-1 text-right"><button type="button" @click="remove(i)" class="text-red-500 hover:underline">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <button type="button" @click="add()" class="mt-2 px-3 py-1 bg-slate-100 hover:bg-slate-200 text-sm rounded">+ Add column</button>
            </div>
        </div>

        <div class="px-4 py-3 bg-slate-50 border-t flex justify-end gap-2">
            <a href="{{ route('dblens.database.show', ['connection' => $connection]) }}" class="px-4 py-2 text-sm text-slate-600">Cancel</a>
            <button class="px-4 py-2 bg-emerald-600 text-white rounded text-sm">Create</button>
        </div>
    </form>
</div>
@endsection
