<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\ErViewStorage;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class ErController extends Controller
{
    public function show(string $connection, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        $driver = $cm->driver($connection);

        $tables = $schema->tables($connection);
        $columnsByTable = $driver->allColumns();
        $allFks = $driver->allForeignKeys();

        // Build referenced-column set per table so we can mark which columns
        // are "key" (PK or FK or referenced by another table) — these stay
        // visible by default in the compact view.
        $referenced = []; // [table => [col,col,…]]
        foreach ($allFks as $fk) {
            $referenced[$fk['foreign_table']][] = $fk['foreign_column'];
        }

        $diagram = [];
        foreach ($tables as $t) {
            $tableCols = $columnsByTable[$t['name']] ?? [];
            $diagram[] = [
                'name' => $t['name'],
                'columns' => array_map(function ($c) use ($t, $referenced) {
                    $isPk = stripos((string)($c['key'] ?? ''), 'PRI') !== false;
                    $isRef = in_array($c['name'], $referenced[$t['name']] ?? [], true);
                    return [
                        'name' => $c['name'],
                        'type' => $c['type'],
                        'key' => $c['key'],
                        'is_pk' => $isPk,
                        'is_referenced' => $isRef,
                    ];
                }, $tableCols),
            ];
        }

        // Mark FK columns + dedupe
        $fkCols = []; // [table => [col => true]]
        foreach ($allFks as $fk) {
            $fkCols[$fk['table']][$fk['column']] = true;
        }
        foreach ($diagram as &$t) {
            foreach ($t['columns'] as &$c) {
                $c['is_fk'] = isset($fkCols[$t['name']][$c['name']]);
                $c['is_key'] = $c['is_pk'] || $c['is_fk'] || $c['is_referenced'];
            }
        }
        unset($t, $c);

        $views = app(ErViewStorage::class)->forConnection($connection);

        return view('dblens::schema.er', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $tables,
            'diagram' => $diagram,
            'fks' => $allFks,
            'saved_views' => $views,
        ]);
    }

    public function saveView(string $connection, Request $request, ConnectionManager $cm, ErViewStorage $storage)
    {
        $cm->assertAllowed($connection);
        $data = $request->validate([
            'id' => 'nullable|string|max:32',
            'name' => 'required|string|max:120',
            'state' => 'required|array',
        ]);
        $view = $storage->save([
            'id' => $data['id'] ?? null,
            'name' => $data['name'],
            'connection' => $connection,
            'state' => $data['state'],
        ]);
        return response()->json(['ok' => true, 'view' => $view]);
    }

    public function deleteView(string $connection, string $id, ConnectionManager $cm, ErViewStorage $storage)
    {
        $cm->assertAllowed($connection);
        $existing = $storage->find($id);
        if (! $existing || ($existing['connection'] ?? null) !== $connection) {
            return response()->json(['ok' => false], 404);
        }
        $storage->delete($id);
        return response()->json(['ok' => true]);
    }
}
