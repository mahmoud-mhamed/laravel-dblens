<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\Exporter;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class ExportController extends Controller
{
    protected function assertEnabled(): void
    {
        abort_unless(config('dblens.allow_export', true), 403, 'Export is disabled.');
    }

    public function table(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, Exporter $exporter)
    {
        $this->assertEnabled();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $format = strtolower((string) $request->query('format', 'sql'));
        if (! in_array($format, ['sql', 'csv', 'json'], true)) {
            abort(422, 'Unsupported format.');
        }
        return $exporter->tableResponse($connection, $table, $format);
    }

    public function database(string $connection, ConnectionManager $cm, Exporter $exporter)
    {
        $this->assertEnabled();
        $cm->assertAllowed($connection);
        return $exporter->databaseResponse($connection);
    }

    public function custom(string $connection, Request $request, ConnectionManager $cm, Exporter $exporter)
    {
        $this->assertEnabled();
        $cm->assertAllowed($connection);

        $decode = function ($v) {
            if (is_string($v) && $v !== '') {
                $j = json_decode($v, true);
                if (is_array($j)) return array_values(array_filter(array_map('strval', $j)));
            }
            if (is_array($v)) return array_values(array_filter(array_map('strval', $v)));
            return [];
        };

        $structure = $decode($request->input('tables_structure_json', $request->input('tables_structure', [])));
        $data = $decode($request->input('tables_data_json', $request->input('tables_data', [])));

        $format = strtolower((string) $request->input('format', 'sql'));
        if (! in_array($format, ['sql', 'csv', 'json'], true)) $format = 'sql';

        // CSV/JSON only export data
        if ($format === 'sql' && empty($structure) && empty($data)) {
            return back()->with('dblens.error', 'No tables selected.');
        }
        if (in_array($format, ['csv', 'json'], true) && empty($data)) {
            return back()->with('dblens.error', 'Select at least one Data table for ' . strtoupper($format) . ' export.');
        }

        return $exporter->custom($connection, $structure, $data, $format);
    }
}
