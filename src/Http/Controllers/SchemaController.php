<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Http\Requests\AddColumnRequest;
use MahmoudMhamed\DbLens\Http\Requests\AddForeignKeyRequest;
use MahmoudMhamed\DbLens\Http\Requests\AddIndexRequest;
use MahmoudMhamed\DbLens\Http\Requests\CreateTableRequest;
use MahmoudMhamed\DbLens\Http\Requests\ModifyColumnRequest;
use MahmoudMhamed\DbLens\Http\Requests\RenameColumnRequest;
use MahmoudMhamed\DbLens\Http\Requests\RenameTableRequest;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;
use MahmoudMhamed\DbLens\Services\TableEditor;
use MahmoudMhamed\DbLens\Support\Concerns\AssertsWritable;

class SchemaController extends Controller
{
    use AssertsWritable;

    protected bool $writableFailsWith403 = true;

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

    public function addColumn(string $connection, string $table, AddColumnRequest $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $name = (string) $request->input('name');
        try {
            $editor->addColumn($connection, $table, $name, $request->columnDefinition());
        } catch (\Throwable $e) { return $this->backWithError($e); }

        return $this->backToStructure($connection, $table, "Column [{$name}] added.");
    }

    public function modifyColumn(string $connection, string $table, string $column, ModifyColumnRequest $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        try {
            $editor->modifyColumn($connection, $table, $column, $request->columnDefinition());
        } catch (\Throwable $e) { return $this->backWithError($e); }

        return $this->backToStructure($connection, $table, "Column [{$column}] modified.");
    }

    public function renameColumn(string $connection, string $table, string $column, RenameColumnRequest $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $to = (string) $request->input('to');
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

    public function addIndex(string $connection, string $table, AddIndexRequest $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $name = (string) $request->input('name');
        $columns = (array) $request->input('columns');
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

    public function addForeignKey(string $connection, string $table, AddForeignKeyRequest $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);

        $name = (string) $request->input('name');
        $column = (string) $request->input('column');
        $refTable = (string) $request->input('foreign_table');
        $refColumn = (string) $request->input('foreign_column');
        $onUpdate = $request->input('on_update') ?: null;
        $onDelete = $request->input('on_delete') ?: null;

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

    public function deleteAllRows(string $connection, string $table, Request $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        abort_unless(config('dblens.allow_delete_all', true), 403, 'DELETE ALL is disabled.');
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $this->confirmed($request);
        $fkMode = (string) $request->input('fk_mode', 'none');
        if (! in_array($fkMode, ['none', 'disable_checks', 'cascade'], true)) {
            $fkMode = 'none';
        }
        try {
            $result = $editor->deleteAllRows($connection, $table, $fkMode);
        } catch (\Throwable $e) { return $this->backWithError($e); }

        $message = "Deleted {$result['affected']} row(s) from [{$table}].";
        if (! empty($result['related'])) {
            $message .= ' Also removed ' . array_sum($result['related'])
                . ' related row(s) across ' . count($result['related']) . ' table(s).';
        }
        return redirect()
            ->route('dblens.table.browse', array_merge($request->query(), ['connection' => $connection, 'table' => $table]))
            ->with('dblens.success', $message);
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

    public function renameTable(string $connection, string $table, RenameTableRequest $request, ConnectionManager $cm, SchemaInspector $schema, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        abort_unless($schema->tableExists($connection, $table), 404);
        $to = (string) $request->input('to');
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

    public function createTable(string $connection, CreateTableRequest $request, ConnectionManager $cm, TableEditor $editor)
    {
        $this->assertWritable();
        $cm->assertAllowed($connection);
        $name = (string) $request->input('name');

        $cols = $request->columnDefinitions();
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
