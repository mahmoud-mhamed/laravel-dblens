<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class AboutController extends Controller
{
    public function show(ConnectionManager $cm, SchemaInspector $schema)
    {
        $connection = $cm->default();
        $database = null;
        $tables = [];
        try {
            $database = $cm->databaseName($connection);
            $tables = $schema->tables($connection);
        } catch (\Throwable $e) {
            // Don't crash the about page just because the DB is unreachable.
        }

        return view('dblens::about', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $database,
            'tables' => $tables,
            'version' => $this->packageVersion(),
        ]);
    }

    protected function packageVersion(): string
    {
        $composer = dirname(__DIR__, 3).'/composer.json';
        if (! is_file($composer)) return 'dev';
        $data = json_decode((string) file_get_contents($composer), true);
        return (string) ($data['version'] ?? 'dev');
    }
}
