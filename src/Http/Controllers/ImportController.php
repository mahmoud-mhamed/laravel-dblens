<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\Importer;
use MahmoudMhamed\DbLens\Services\SchemaInspector;
use MahmoudMhamed\DbLens\Support\Concerns\AssertsWritable;

class ImportController extends Controller
{
    use AssertsWritable {
        AssertsWritable::assertWritable as protected assertNotReadOnly;
    }

    protected bool $writableFailsWith403 = true;

    protected function assertWritable(): void
    {
        $this->assertNotReadOnly();
        abort_unless(config('dblens.allow_import', true), 403, 'Import is disabled.');
    }

    public function form(ConnectionManager $cm, SchemaInspector $schema, string $connection, ?string $table = null)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        if ($table !== null) abort_unless($schema->tableExists($connection, $table), 404);

        return view('dblens::import.form', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
        ]);
    }

    public function importSql(string $connection, Request $request, ConnectionManager $cm, Importer $importer)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        $file = $request->file('sql_file');
        if (! $file) return back()->with('dblens.error', 'No SQL file uploaded.');

        try {
            $stats = $importer->importSql($connection, $file->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('dblens.error', $e->getMessage());
        }
        app(\MahmoudMhamed\DbLens\Services\ActivityLogger::class)->log(
            'import_sql',
            "Imported {$stats['executed']}/{$stats['statements']} statements from {$file->getClientOriginalName()}",
            [
                'connection' => $connection,
                'filename' => $file->getClientOriginalName(),
                'statements' => $stats['statements'],
                'executed' => $stats['executed'],
            ],
        );
        return redirect()
            ->route('dblens.database.show', ['connection' => $connection])
            ->with('dblens.success', "SQL imported — {$stats['executed']}/{$stats['statements']} statements.");
    }

    public function importCsv(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, Importer $importer)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $file = $request->file('csv_file');
        if (! $file) return back()->with('dblens.error', 'No CSV file uploaded.');

        $hasHeader = $request->boolean('has_header', true);
        $delim = (string) $request->input('delimiter', ',');
        if (mb_strlen($delim) !== 1) $delim = ',';

        try {
            $stats = $importer->importCsv($connection, $table, $file->getRealPath(), [], $hasHeader, $delim);
        } catch (\Throwable $e) {
            return back()->with('dblens.error', $e->getMessage());
        }
        app(\MahmoudMhamed\DbLens\Services\ActivityLogger::class)->log(
            'import_csv',
            "Imported {$stats['inserted']} CSV row(s) into {$table} from {$file->getClientOriginalName()}",
            [
                'connection' => $connection,
                'table' => $table,
                'filename' => $file->getClientOriginalName(),
                'inserted' => $stats['inserted'],
            ],
        );
        return redirect()
            ->route('dblens.table.browse', ['connection' => $connection, 'table' => $table])
            ->with('dblens.success', "CSV imported — {$stats['inserted']} row(s).");
    }
}
