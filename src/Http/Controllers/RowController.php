<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\ModelCastResolver;
use MahmoudMhamed\DbLens\Services\QueryRunner;
use MahmoudMhamed\DbLens\Services\RowEditor;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class RowController extends Controller
{
    public function show(string $connection, string $table, string $rowKey, ConnectionManager $cm, SchemaInspector $schema, QueryRunner $runner)
    {
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $pkCols = $schema->primaryKey($connection, $table);
        $pkValues = $this->decodeRowKey($rowKey, $pkCols);
        if (empty($pkValues)) abort(404);

        $row = $runner->findRow($connection, $table, $pkValues);
        abort_if($row === null, 404);

        $columns = $schema->columns($connection, $table);
        $foreignKeys = $schema->foreignKeyByColumn($connection, $table);
        $incoming = $schema->incomingForeignKeys($connection, $table);

        $incomingCounts = [];
        $incomingPreviews = [];
        $previewLimit = (int) config('dblens.row.related_preview_limit', 5);
        $driver = $cm->driver($connection);
        foreach ($incoming as $fk) {
            $foreignVal = $row[$fk['foreign_column']] ?? null;
            $incomingCounts[$fk['name']] = 0;
            $incomingPreviews[$fk['name']] = ['columns' => [], 'rows' => [], 'pk' => []];
            if ($foreignVal === null) continue;
            try {
                $qt = $driver->quoteIdentifier($fk['table']);
                $qc = $driver->quoteIdentifier($fk['column']);
                $incomingCounts[$fk['name']] = (int) ($driver->connection()->selectOne("SELECT COUNT(*) as c FROM {$qt} WHERE {$qc} = ?", [$foreignVal])->c ?? 0);
                if ($incomingCounts[$fk['name']] > 0) {
                    $childCols = $schema->columns($connection, $fk['table']);
                    $childPk = $schema->primaryKey($connection, $fk['table']);
                    // Pick a small, useful subset of columns to preview.
                    $pickNames = [];
                    foreach (['id','name','title','label','code','email','created_at','status','type'] as $cand) {
                        foreach ($childCols as $c) if ($c['name'] === $cand) { $pickNames[$cand] = true; break; }
                    }
                    foreach ($childPk as $c) $pickNames[$c] = true;
                    $pickNames[$fk['column']] = true;
                    $pick = array_keys($pickNames);
                    $qcols = implode(',', array_map(fn ($c) => $driver->quoteIdentifier($c), $pick));
                    $previewRows = $driver->connection()->select(
                        "SELECT {$qcols} FROM {$qt} WHERE {$qc} = ? LIMIT {$previewLimit}",
                        [$foreignVal]
                    );
                    $incomingPreviews[$fk['name']] = [
                        'columns' => $pick,
                        'rows' => array_map(fn ($r) => (array) $r, $previewRows),
                        'pk' => $childPk,
                    ];
                }
            } catch (\Throwable $e) {
                $incomingCounts[$fk['name']] = null;
            }
        }

        return view('dblens::row.show', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'columns' => $columns,
            'row' => $row,
            'primary_key' => $pkCols,
            'pk_values' => $pkValues,
            'row_key' => $this->encodeRowKey($pkValues),
            'foreign_keys' => $foreignKeys,
            'incoming_fks' => $incoming,
            'incoming_counts' => $incomingCounts,
            'incoming_previews' => $incomingPreviews,
            'preview_limit' => $previewLimit,
            'read_only' => (bool) config('dblens.read_only', false),
        ]);
    }

    public function create(string $connection, string $table, ConnectionManager $cm, SchemaInspector $schema, ModelCastResolver $casts)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        return view('dblens::row.form', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'columns' => $schema->columns($connection, $table),
            'foreign_keys' => $schema->foreignKeyByColumn($connection, $table),
            'primary_key' => $schema->primaryKey($connection, $table),
            'enum_casts' => $this->enumCastsForTable($casts, $table),
            'mode' => 'create',
            'row' => [],
            'row_key' => null,
        ]);
    }

    public function store(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, RowEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        try {
            $pkValues = $editor->insert($connection, $table, (array) $request->input('row', []));
        } catch (\Throwable $e) {
            return back()->withInput()->with('dblens.error', $e->getMessage());
        }

        if (! empty($pkValues)) {
            return redirect()
                ->route('dblens.row.show', ['connection' => $connection, 'table' => $table, 'rowKey' => $this->encodeRowKey($pkValues)])
                ->with('dblens.success', 'Row inserted.');
        }
        return redirect()
            ->route('dblens.table.browse', ['connection' => $connection, 'table' => $table])
            ->with('dblens.success', 'Row inserted.');
    }

    public function edit(string $connection, string $table, string $rowKey, ConnectionManager $cm, SchemaInspector $schema, QueryRunner $runner, ModelCastResolver $casts)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $pkCols = $schema->primaryKey($connection, $table);
        $pkValues = $this->decodeRowKey($rowKey, $pkCols);
        if (empty($pkValues)) abort(404);

        $row = $runner->findRow($connection, $table, $pkValues);
        abort_if($row === null, 404);

        return view('dblens::row.form', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'columns' => $schema->columns($connection, $table),
            'foreign_keys' => $schema->foreignKeyByColumn($connection, $table),
            'primary_key' => $pkCols,
            'enum_casts' => $this->enumCastsForTable($casts, $table),
            'mode' => 'edit',
            'row' => $row,
            'pk_values' => $pkValues,
            'row_key' => $this->encodeRowKey($pkValues),
        ]);
    }

    /**
     * @return array<string,array<int,array{value:mixed,name:string,label:string}>>
     *         column => cases
     */
    protected function enumCastsForTable(ModelCastResolver $resolver, string $table): array
    {
        $byColumn = $resolver->getEnumCasts()[$table] ?? [];
        $out = [];
        foreach ($byColumn as $col => $enumClass) {
            $out[$col] = $resolver->enumCases($enumClass);
        }
        return $out;
    }

    public function update(string $connection, string $table, string $rowKey, Request $request, ConnectionManager $cm, SchemaInspector $schema, RowEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $pkCols = $schema->primaryKey($connection, $table);
        $pkValues = $this->decodeRowKey($rowKey, $pkCols);
        if (empty($pkValues)) abort(404);

        try {
            $editor->update($connection, $table, $pkValues, (array) $request->input('row', []));
        } catch (\Throwable $e) {
            return back()->withInput()->with('dblens.error', $e->getMessage());
        }

        return redirect()
            ->route('dblens.row.show', ['connection' => $connection, 'table' => $table, 'rowKey' => $rowKey])
            ->with('dblens.success', 'Row updated.');
    }

    public function destroy(string $connection, string $table, string $rowKey, Request $request, ConnectionManager $cm, SchemaInspector $schema, RowEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        if (config('dblens.confirm_destructive', true) && ! $request->boolean('confirm')) {
            abort(422, 'Confirmation required.');
        }

        $pkCols = $schema->primaryKey($connection, $table);
        $pkValues = $this->decodeRowKey($rowKey, $pkCols);
        if (empty($pkValues)) abort(404);

        try {
            $editor->delete($connection, $table, $pkValues);
        } catch (\Throwable $e) {
            return back()->with('dblens.error', $e->getMessage());
        }

        return redirect()
            ->route('dblens.table.browse', ['connection' => $connection, 'table' => $table])
            ->with('dblens.success', 'Row deleted.');
    }

    public function bulkDestroy(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, RowEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        if (config('dblens.confirm_destructive', true) && ! $request->boolean('confirm')) {
            abort(422, 'Confirmation required.');
        }

        $pkCols = $schema->primaryKey($connection, $table);
        $keys = (array) $request->input('keys', []);
        $sets = [];
        foreach ($keys as $k) {
            $vals = $this->decodeRowKey((string) $k, $pkCols);
            if (! empty($vals)) $sets[] = $vals;
        }
        if (empty($sets)) {
            return back()->with('dblens.error', 'No rows selected.');
        }

        try {
            $count = $editor->bulkDelete($connection, $table, $sets);
        } catch (\Throwable $e) {
            return back()->with('dblens.error', $e->getMessage());
        }

        return redirect()
            ->route('dblens.table.browse', ['connection' => $connection, 'table' => $table])
            ->with('dblens.success', "{$count} row(s) deleted.");
    }

    public function updateCell(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, RowEditor $editor)
    {
        if (config('dblens.read_only', false)) {
            return response()->json(['message' => 'DbLens is in read-only mode.'], 403);
        }
        $cm->assertAllowed($connection);
        if (! $schema->tableExists($connection, $table)) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        $rowKey = (string) $request->input('row_key', '');
        $column = (string) $request->input('column', '');
        $value = $request->input('value');

        $pkCols = $schema->primaryKey($connection, $table);
        $pkValues = $this->decodeRowKey($rowKey, $pkCols);
        if (empty($pkValues)) {
            return response()->json(['message' => 'Invalid row key.'], 422);
        }

        $columns = $schema->columns($connection, $table);
        $colNames = array_map(fn ($c) => $c['name'], $columns);
        if (! in_array($column, $colNames, true)) {
            return response()->json(['message' => 'Unknown column.'], 422);
        }
        $masked = array_map('strtolower', (array) config('dblens.masked_columns', []));
        if (in_array(strtolower($column), $masked, true)) {
            return response()->json(['message' => 'Cannot edit masked column inline.'], 422);
        }

        try {
            $editor->update($connection, $table, $pkValues, [$column => $value]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'value' => $value]);
    }

    public function fkOptions(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        if (! $schema->tableExists($connection, $table)) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        $column = (string) $request->query('column', '');
        $search = trim((string) $request->query('q', ''));

        $fks = $schema->foreignKeyByColumn($connection, $table);
        if (! isset($fks[$column])) {
            return response()->json(['message' => 'Column is not a foreign key.'], 422);
        }
        $fk = $fks[$column];
        $foreignTable = $fk['foreign_table'];
        $foreignCol = $fk['foreign_column'];

        $foreignCols = $schema->columns($connection, $foreignTable);
        $foreignColNames = array_map(fn ($c) => $c['name'], $foreignCols);

        // Pick a "label" column heuristically (in priority order)
        $labelCandidates = [
            'name', 'title', 'full_name', 'display_name', 'fullname', 'displayname',
            'label', 'subject', 'username', 'email', 'slug', 'code', 'reference', 'identifier',
            'first_name', 'description',
        ];
        $labelCol = null;
        foreach ($labelCandidates as $cand) {
            if (in_array($cand, $foreignColNames, true)) { $labelCol = $cand; break; }
        }

        $driver = $cm->driver($connection);
        $qt = $driver->quoteIdentifier($foreignTable);
        $qFc = $driver->quoteIdentifier($foreignCol);

        $select = [$qFc];
        if ($labelCol !== null && $labelCol !== $foreignCol) {
            $select[] = $driver->quoteIdentifier($labelCol) . ' as label';
        }
        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$qt}";
        $bindings = [];
        if ($search !== '') {
            $sql .= " WHERE CAST({$qFc} AS CHAR) LIKE ?";
            $bindings[] = "%{$search}%";
            if ($labelCol !== null && $labelCol !== $foreignCol) {
                $sql .= " OR " . $driver->quoteIdentifier($labelCol) . ' LIKE ?';
                $bindings[] = "%{$search}%";
            }
        }
        $sql .= " LIMIT 100";

        try {
            $rows = $driver->connection()->select($sql, $bindings);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $options = array_map(function ($r) use ($foreignCol, $labelCol) {
            $arr = (array) $r;
            $val = $arr[$foreignCol] ?? null;
            $label = $labelCol && isset($arr['label']) ? (string) $arr['label'] : null;
            return [
                'value' => $val,
                'label' => $label !== null ? ($val . ' — ' . $label) : (string) $val,
            ];
        }, $rows);

        return response()->json([
            'options' => $options,
            'label_column' => $labelCol,
            'foreign_table' => $foreignTable,
            'foreign_column' => $foreignCol,
        ]);
    }

    protected function assertWritable(): void
    {
        if (config('dblens.read_only', false)) {
            abort(403, 'DbLens is in read-only mode.');
        }
    }

    protected function decodeRowKey(string $rowKey, array $pkCols): array
    {
        $decoded = rawurldecode($rowKey);
        $json = json_decode($decoded, true);
        if (is_array($json)) return $json;
        if (count($pkCols) === 1) return [$pkCols[0] => $decoded];
        return [];
    }

    protected function encodeRowKey(array $pkValues): string
    {
        if (count($pkValues) === 1) {
            return rawurlencode((string) reset($pkValues));
        }
        return rawurlencode(json_encode($pkValues));
    }
}
