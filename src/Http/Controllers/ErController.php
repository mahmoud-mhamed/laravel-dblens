<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
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

        return view('dblens::schema.er', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $tables,
            'diagram' => $diagram,
            'fks' => $allFks,
        ]);
    }
}
