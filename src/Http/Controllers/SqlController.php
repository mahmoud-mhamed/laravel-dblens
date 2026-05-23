<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\QueryRunner;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class SqlController extends Controller
{
    public function show(string $connection, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        return view('dblens::sql.editor', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'sql' => session('dblens.sql', ''),
            'result' => session('dblens.sql_result'),
            'error' => session('dblens.sql_error'),
        ]);
    }

    public function run(string $connection, Request $request, ConnectionManager $cm, QueryRunner $runner)
    {
        $cm->assertAllowed($connection);
        $sql = (string) $request->input('sql', '');
        $action = $request->input('action', 'run');
        $error = null;
        $result = null;
        try {
            $result = $action === 'explain'
                ? $runner->explain($connection, $sql)
                : $runner->runRaw($connection, $sql);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        return redirect()
            ->route('dblens.sql.show', ['connection' => $connection])
            ->with('dblens.sql', $sql)
            ->with('dblens.sql_result', $result)
            ->with('dblens.sql_error', $error)
            ->with('dblens.sql_action', $action);
    }
}
