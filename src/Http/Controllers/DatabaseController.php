<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\QueryRunner;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class DatabaseController extends Controller
{
    public function show(string $connection, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        $tables = $schema->tables($connection);
        return view('dblens::database.show', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $tables,
        ]);
    }

    public function search(string $connection, Request $request, ConnectionManager $cm, QueryRunner $runner, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        $term = (string) $request->query('q', '');
        $results = [];
        if ($term !== '') {
            $results = $runner->globalSearch($connection, $term);
        }
        return view('dblens::database.search', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'term' => $term,
            'results' => $results,
        ]);
    }
}
