<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;
use MahmoudMhamed\DbLens\Services\TableEditor;

class SchemaController extends Controller
{
    protected function assertWritable(): void
    {
        if (config('dblens.read_only', false)) {
            abort(403, 'DbLens is in read-only mode.');
        }
    }

    protected function confirmed(Request $request): void
    {
        if (config('dblens.confirm_destructive', true) && ! $request->boolean('confirm')) {
            abort(422, 'Confirmation required.');
        }
    }

    protected function backToStructure(string $connection, string $table, string $message)
    {
        return redirect()
            ->route('dblens.table.structure', ['connection' => $connection, 'table' => $table])
            ->with('dblens.success', $message);
    }

    protected function backWithError(\Throwable $e)
    {
        return back()->with('dblens.error', $e->getMessage());
    }

    // ─── columns ────────────────────────────────────────────────────────────

    public function addColumn(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $name = trim((string) $request->input('name'));
        $type = trim((string) $request->input('type'));
        if ($name === '' || $type === '') {
            return $this->backWithError(new \RuntimeException('Name and type are required.'));
        }
        try {
            $editor->addColumn($connection, $table, $name, [
                'type' => $type,
                'nullable' => $request->boolean('nullable'),
                'default' => $request->input('default'),
                'after' => $request->input('after'),
                'comment' => $request->input('comment'),
            ]);
        } catch (\Throwable $e) { return $this->backWithError($e); }

        return $this->backToStructure($connection, $table, "Column [{$name}] added.");
    }

    public function modifyColumn(string $connection, string $table, string $column, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        try {
            $editor->modifyColumn($connection, $table, $column, [
                'type' => trim((string) $request->input('type')),
                'nullable' => $request->boolean('nullable'),
                'default' => $request->input('default'),
                'comment' => $request->input('comment'),
            ]);
        } catch (\Throwable $e) { return $this->backWithError($e); }

        return $this->backToStructure($connection, $table, "Column [{$column}] modified.");
    }

    public function renameColumn(string $connection, string $table, string $column, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $to = trim((string) $request->input('to'));
        if ($to === '') return $this->backWithError(new \RuntimeException('New name is required.'));
        try {
            $editor->renameColumn($connection, $table, $column, $to);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return $this->backToStructure($connection, $table, "Renamed [{$column}] → [{$to}].");
    }

    public function dropColumn(string $connection, string $table, string $column, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $this->confirmed($request);
        try {
            $editor->dropColumn($connection, $table, $column);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return $this->backToStructure($connection, $table, "Column [{$column}] dropped.");
    }

    // ─── indexes ────────────────────────────────────────────────────────────

    public function addIndex(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $name = trim((string) $request->input('name'));
        $columns = array_filter(array_map('trim', (array) $request->input('columns', [])));
        if ($name === '' || empty($columns)) {
            return $this->backWithError(new \RuntimeException('Name and at least one column are required.'));
        }
        try {
            $editor->addIndex($connection, $table, $name, $columns, $request->boolean('unique'));
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return $this->backToStructure($connection, $table, "Index [{$name}] added.");
    }

    public function dropIndex(string $connection, string $table, string $index, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $this->confirmed($request);
        try {
            $editor->dropIndex($connection, $table, $index);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return $this->backToStructure($connection, $table, "Index [{$index}] dropped.");
    }

    // ─── foreign keys ──────────────────────────────────────────────────────

    public function addForeignKey(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $name = trim((string) $request->input('name'));
        $column = trim((string) $request->input('column'));
        $refTable = trim((string) $request->input('foreign_table'));
        $refColumn = trim((string) $request->input('foreign_column'));
        if ($name === '' || $column === '' || $refTable === '' || $refColumn === '') {
            return $this->backWithError(new \RuntimeException('All foreign-key fields are required.'));
        }
        $allowedActions = ['CASCADE', 'SET NULL', 'NO ACTION', 'RESTRICT'];
        $onUpdate = in_array($request->input('on_update'), $allowedActions, true) ? $request->input('on_update') : null;
        $onDelete = in_array($request->input('on_delete'), $allowedActions, true) ? $request->input('on_delete') : null;

        try {
            $editor->addForeignKey($connection, $table, $name, $column, $refTable, $refColumn, $onUpdate, $onDelete);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return $this->backToStructure($connection, $table, "Foreign key [{$name}] added.");
    }

    // ─── table-level ───────────────────────────────────────────────────────

    public function truncate(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        abort_unless(config('dblens.allow_truncate', true), 403, 'TRUNCATE is disabled.');
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $this->confirmed($request);
        try {
            $editor->truncate($connection, $table);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return $this->backToStructure($connection, $table, "Table [{$table}] truncated.");
    }

    public function dropTable(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        abort_unless(config('dblens.allow_drop_table', true), 403, 'DROP TABLE is disabled.');
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $this->confirmed($request);
        try {
            $editor->drop($connection, $table);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return redirect()
            ->route('dblens.database.show', ['connection' => $connection])
            ->with('dblens.success', "Table [{$table}] dropped.");
    }

    public function renameTable(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $to = trim((string) $request->input('to'));
        if ($to === '') return $this->backWithError(new \RuntimeException('New name is required.'));
        try {
            $editor->rename($connection, $table, $to);
        } catch (\Throwable $e) { return $this->backWithError($e); }
        return redirect()
            ->route('dblens.table.structure', ['connection' => $connection, 'table' => $to])
            ->with('dblens.success', "Table renamed to [{$to}].");
    }

    public function createTableForm(string $connection, ConnectionManager $cm, SchemaInspector $schema)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        return view('dblens::table.create', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
        ]);
    }

    public function createTable(string $connection, Request $request, ConnectionManager $cm, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        $name = trim((string) $request->input('name'));
        if ($name === '') return back()->withInput()->with('dblens.error', 'Table name is required.');

        $cols = [];
        foreach ((array) $request->input('columns', []) as $c) {
            $cn = trim((string) ($c['name'] ?? ''));
            $ct = trim((string) ($c['type'] ?? ''));
            if ($cn === '' || $ct === '') continue;
            $cols[] = [
                'name' => $cn,
                'type' => $ct,
                'nullable' => ! empty($c['nullable']),
                'default' => $c['default'] ?? null,
                'primary' => ! empty($c['primary']),
                'auto_increment' => ! empty($c['auto_increment']),
            ];
        }
        if (empty($cols)) {
            return back()->withInput()->with('dblens.error', 'At least one column is required.');
        }
        try {
            $editor->create($connection, $name, $cols);
        } catch (\Throwable $e) {
            return back()->withInput()->with('dblens.error', $e->getMessage());
        }
        return redirect()
            ->route('dblens.table.structure', ['connection' => $connection, 'table' => $name])
            ->with('dblens.success', "Table [{$name}] created.");
    }
}
