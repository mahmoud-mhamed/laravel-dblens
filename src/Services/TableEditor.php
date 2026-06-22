<?php

namespace MahmoudMhamed\DbLens\Services;

use MahmoudMhamed\DbLens\Support\Concerns\AssertsWritable;
use RuntimeException;

class TableEditor
{
    use AssertsWritable;

    public function __construct(
        protected ConnectionManager $cm,
        protected ?ActivityLogger $logger = null,
        protected ?SchemaInspector $schema = null,
    ) {}

    protected function logger(): ActivityLogger
    {
        return $this->logger ??= app(ActivityLogger::class);
    }

    protected function schema(): SchemaInspector
    {
        return $this->schema ??= app(SchemaInspector::class);
    }

    protected function invalidate(string $connection): void
    {
        $this->schema()->flush($connection);
    }

    protected function log(string $event, string $description, string $connection, string $table, array $props = []): void
    {
        $this->logger()->log($event, $description, array_merge([
            'connection' => $connection,
            'table' => $table,
        ], $props));
        $this->invalidate($connection);
    }

    public function addColumn(string $connection, string $table, string $column, array $def): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->addColumn($table, $column, $def);
        $this->log('schema_column_added', "Added column {$column} to {$table}", $connection, $table, ['column' => $column, 'definition' => $def]);
    }

    public function modifyColumn(string $connection, string $table, string $column, array $def): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->modifyColumn($table, $column, $def);
        $this->log('schema_column_modified', "Modified column {$column} on {$table}", $connection, $table, ['column' => $column, 'definition' => $def]);
    }

    public function renameColumn(string $connection, string $table, string $from, string $to): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->renameColumn($table, $from, $to);
        $this->log('schema_column_renamed', "Renamed column {$from} → {$to} on {$table}", $connection, $table, ['from' => $from, 'to' => $to]);
    }

    public function dropColumn(string $connection, string $table, string $column): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->dropColumn($table, $column);
        $this->log('schema_column_dropped', "Dropped column {$column} from {$table}", $connection, $table, ['column' => $column]);
    }

    public function addIndex(string $connection, string $table, string $name, array $columns, bool $unique = false): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->addIndex($table, $name, $columns, $unique);
        $this->log('schema_index_added', "Added index {$name} on {$table}", $connection, $table, ['name' => $name, 'columns' => $columns, 'unique' => $unique]);
    }

    public function dropIndex(string $connection, string $table, string $name): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->dropIndex($table, $name);
        $this->log('schema_index_dropped', "Dropped index {$name} from {$table}", $connection, $table, ['name' => $name]);
    }

    public function addForeignKey(string $connection, string $table, string $name, string $column, string $refTable, string $refColumn, ?string $onUpdate, ?string $onDelete): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->addForeignKey($table, $name, $column, $refTable, $refColumn, $onUpdate, $onDelete);
        $this->log('schema_fk_added', "Added FK {$name} on {$table}.{$column} → {$refTable}.{$refColumn}", $connection, $table, [
            'name' => $name, 'column' => $column,
            'references' => "{$refTable}.{$refColumn}",
            'on_update' => $onUpdate, 'on_delete' => $onDelete,
        ]);
    }

    public function truncate(string $connection, string $table): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->truncateTable($table);
        $this->log('schema_table_truncated', "Truncated {$table}", $connection, $table);
    }

    /**
     * Delete every row via DELETE FROM (not TRUNCATE). Slower than truncate,
     * but respects triggers, runs in a transaction and returns row counts.
     *
     * $fkMode controls how foreign-key references are handled:
     *   'none'           — plain DELETE FROM (errors if referenced elsewhere).
     *   'disable_checks' — turn FK constraint checking off for the delete; the
     *                      target table only (referencing rows are orphaned).
     *   'cascade'        — recursively delete rows in every table that points
     *                      at this one first, then this table (keeps integrity).
     *
     * @return array{table:string, affected:int, related:array<string,int>, total:int}
     */
    public function deleteAllRows(string $connection, string $table, string $fkMode = 'none'): array
    {
        $this->assertWritable();
        $driver = $this->cm->driver($connection);
        $conn = $driver->connection();
        $sql = 'DELETE FROM ' . $driver->quoteIdentifier($table);

        if ($fkMode === 'cascade') {
            $counts = [];
            $conn->transaction(function () use ($connection, $table, &$counts) {
                $this->cascadeDeleteRows($connection, $table, $counts, [$table]);
            });
            $affected = (int) ($counts[$table] ?? 0);
            unset($counts[$table]);
            $this->log('row_all_deleted', "Deleted all rows ({$affected}) from {$table} + related", $connection, $table, [
                'affected' => $affected, 'fk_mode' => $fkMode, 'related' => $counts,
            ]);
            return ['table' => $table, 'affected' => $affected, 'related' => $counts, 'total' => $affected + array_sum($counts)];
        }

        if ($fkMode === 'disable_checks') {
            $affected = (int) $conn->getSchemaBuilder()->withoutForeignKeyConstraints(fn () => $conn->delete($sql));
            $this->log('row_all_deleted', "Deleted all rows ({$affected}) from {$table} [FK checks off]", $connection, $table, [
                'affected' => $affected, 'fk_mode' => $fkMode,
            ]);
            return ['table' => $table, 'affected' => $affected, 'related' => [], 'total' => $affected];
        }

        $affected = (int) $conn->delete($sql);
        $this->log('row_all_deleted', "Deleted all rows ({$affected}) from {$table}", $connection, $table, [
            'affected' => $affected, 'fk_mode' => 'none',
        ]);
        return ['table' => $table, 'affected' => $affected, 'related' => [], 'total' => $affected];
    }

    /**
     * Depth-first: delete child tables (those referencing $table) before the
     * table itself. $counts is keyed by table → rows deleted; $stack guards
     * against FK cycles. Self-references are left to the table's own delete.
     *
     * @param  array<string,int>  $counts
     * @param  array<int,string>  $stack
     */
    protected function cascadeDeleteRows(string $connection, string $table, array &$counts, array $stack): void
    {
        if (array_key_exists($table, $counts)) return; // already handled (diamond deps)
        $driver = $this->cm->driver($connection);

        foreach ($this->schema()->incomingForeignKeys($connection, $table) as $fk) {
            $child = $fk['table'] ?? null;
            if (! $child || $child === $table) continue;   // self-reference: handled below
            if (in_array($child, $stack, true)) continue;  // cycle guard
            $this->cascadeDeleteRows($connection, $child, $counts, array_merge($stack, [$table]));
        }

        $counts[$table] = (int) $driver->connection()->delete('DELETE FROM ' . $driver->quoteIdentifier($table));
    }

    public function drop(string $connection, string $table): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->dropTable($table);
        $this->log('schema_table_dropped', "Dropped table {$table}", $connection, $table);
    }

    public function rename(string $connection, string $from, string $to): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->renameTable($from, $to);
        $this->log('schema_table_renamed', "Renamed table {$from} → {$to}", $connection, $from, ['to' => $to]);
    }

    public function create(string $connection, string $name, array $columns): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->createTable($name, $columns);
        $this->log('schema_table_created', "Created table {$name}", $connection, $name, ['columns' => $columns]);
    }
}
