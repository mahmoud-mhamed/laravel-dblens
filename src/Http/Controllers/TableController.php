<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\ModelCastResolver;
use MahmoudMhamed\DbLens\Services\QueryRunner;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class TableController extends Controller
{
    public function browse(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, QueryRunner $runner, ModelCastResolver $castResolver)
    {
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $filters = (array) $request->query('filters', []);
        $colFilters = (array) $request->query('col_filters', []);
        if (! empty($colFilters)) {
            $colTypes = [];
            foreach ($schema->columns($connection, $table) as $col) {
                $colTypes[$col['name']] = strtolower($col['type']);
            }
            foreach ($colFilters as $col => $val) {
                if ($val === '' || $val === null) continue;
                if (! isset($colTypes[$col])) continue;
                $type = $colTypes[$col];
                $isText = preg_match('/char|text|enum|json|uuid/', $type);
                $op = $isText ? 'LIKE' : '=';
                $value = $isText ? "%{$val}%" : $val;
                $filters[] = ['column' => $col, 'op' => $op, 'value' => $value, 'enabled' => '1'];
            }
        }

        $result = $runner->browse($connection, $table, [
            'page' => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', config('dblens.browse.per_page', 30)),
            'order_by' => $request->query('order_by'),
            'order_dir' => $request->query('order_dir', 'ASC'),
            'search' => (string) $request->query('search', ''),
            'filters' => $filters,
        ]);

        return view('dblens::table.browse', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'columns' => $schema->columns($connection, $table),
            'primary_key' => $schema->primaryKey($connection, $table),
            'foreign_keys' => $schema->foreignKeyByColumn($connection, $table),
            'rows' => $result['rows'],
            'paginator' => $result['paginator'],
            'total' => $result['total'],
            'approximate' => $result['approximate'] ?? false,
            'sql' => $result['sql'],
            'search' => (string) $request->query('search', ''),
            'filters' => (array) $request->query('filters', []),
            'col_filters' => $colFilters,
            'order_by' => $request->query('order_by'),
            'order_dir' => $request->query('order_dir', 'ASC'),
            'enum_casts' => $this->enumCastsForTable($castResolver, $table),
        ]);
    }

    /**
     * @return array<string,array<int,array{value:mixed,name:string,label:string}>>
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

    public function tree(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $fks = $schema->foreignKeys($connection, $table);
        $selfFk = null;
        foreach ($fks as $fk) {
            if ($fk['foreign_table'] === $table) { $selfFk = $fk; break; }
        }
        abort_unless($selfFk, 404, 'Table has no self-referential foreign key.');

        $driver = $cm->driver($connection);
        $columns = $schema->columns($connection, $table);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        // Choose label column: ?label= override, else first sensible candidate, else PK.
        $candidates = ['name','title','label','code','slug','email','username'];
        $label = $request->query('label');
        if (! $label || ! in_array($label, $columnNames, true)) {
            $label = null;
            foreach ($candidates as $c) {
                if (in_array($c, $columnNames, true)) { $label = $c; break; }
            }
        }
        $pk = $schema->primaryKey($connection, $table);
        $pkCol = $pk[0] ?? $selfFk['foreign_column'];
        $label = $label ?: $pkCol;

        $hasDeletedAt = in_array('deleted_at', $columnNames, true);
        $max = min((int) $request->query('limit', 5000), 20000);
        $cols = [$pkCol, $selfFk['column'], $label];
        if ($hasDeletedAt) $cols[] = 'deleted_at';
        $cols = array_values(array_unique(array_filter($cols)));
        $qcols = implode(',', array_map(fn ($c) => $driver->quoteIdentifier($c), $cols));
        $qt = $driver->quoteIdentifier($table);
        $rows = $driver->connection()->select("SELECT {$qcols} FROM {$qt} LIMIT {$max}");
        $rows = array_map(fn ($r) => (array) $r, $rows);
        $truncated = count($rows) >= $max;

        return view('dblens::table.tree', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'pk_col' => $pkCol,
            'parent_col' => $selfFk['column'],
            'label_col' => $label,
            'truncated' => $truncated,
            'label_candidates' => array_values(array_filter($columnNames, fn ($c) => in_array($c, $candidates, true) || $c === $pkCol)),
        ]);
    }

    public function structure(string $connection, string $table, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        return view('dblens::table.structure', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'columns' => $schema->columns($connection, $table),
            'indexes' => $schema->indexes($connection, $table),
            'foreign_keys' => $schema->foreignKeys($connection, $table),
            'incoming_fks' => $schema->incomingForeignKeys($connection, $table),
            'primary_key' => $schema->primaryKey($connection, $table),
            'read_only' => (bool) config('dblens.read_only', false),
        ]);
    }

    public function info(string $connection, string $table, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        return view('dblens::table.info', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'table' => $table,
            'info' => $schema->tableInfo($connection, $table),
            'columns' => $schema->columns($connection, $table),
            'foreign_keys' => $schema->foreignKeys($connection, $table),
            'incoming_fks' => $schema->incomingForeignKeys($connection, $table),
            'indexes' => $schema->indexes($connection, $table),
            'primary_key' => $schema->primaryKey($connection, $table),
        ]);
    }

    public function dropForeignKey(string $connection, string $table, string $fk, Request $request, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        if (config('dblens.read_only', false)) {
            abort(403, 'DbLens is in read-only mode.');
        }
        if (config('dblens.confirm_destructive', true) && ! $request->boolean('confirm')) {
            abort(422, 'Confirmation required.');
        }

        $cm->driver($connection)->dropForeignKey($table, $fk);
        app(\MahmoudMhamed\DbLens\Services\ActivityLogger::class)->log(
            'schema_fk_dropped',
            "Dropped FK {$fk} from {$table}",
            ['connection' => $connection, 'table' => $table, 'name' => $fk]
        );

        return redirect()
            ->route('dblens.table.structure', ['connection' => $connection, 'table' => $table])
            ->with('dblens.success', "Foreign key [{$fk}] dropped.");
    }
}
