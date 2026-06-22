<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Support\Facades\Hash;
use MahmoudMhamed\DbLens\Support\Concerns\AssertsWritable;
use MahmoudMhamed\DbLens\Support\Sql\PkClause;
use RuntimeException;

class RowEditor
{
    use AssertsWritable;

    public function __construct(
        protected ConnectionManager $cm,
        protected SchemaInspector $schema,
        protected ?ActivityLogger $logger = null,
        protected ?ModelCastResolver $casts = null,
    ) {}

    protected function casts(): ModelCastResolver
    {
        return $this->casts ??= app(ModelCastResolver::class);
    }

    /**
     * Bcrypt any payload columns whose Eloquent model declares a `hashed` cast.
     * Skips values that already look hashed (bcrypt / argon prefixes) so re-saving
     * an existing row doesn't double-hash. Disable via `dblens.auto_hash`.
     */
    protected function applyHashedCasts(string $table, array $payload): array
    {
        if (! config('dblens.auto_hash', true)) {
            return $payload;
        }
        $hashed = $this->casts()->hashedColumns($table);
        if (empty($hashed)) {
            return $payload;
        }
        foreach ($hashed as $col => $_) {
            if (! array_key_exists($col, $payload)) continue;
            $val = $payload[$col];
            if ($val === null || $val === '') continue;
            if (! is_string($val)) continue;
            if (preg_match('/^\$(2[aby]|argon2(i|id)?)\$/', $val)) continue;
            $payload[$col] = Hash::make($val);
        }
        return $payload;
    }

    protected function logger(): ActivityLogger
    {
        return $this->logger ??= app(ActivityLogger::class);
    }

    /**
     * Coerce a posted form value to a PHP value appropriate for binding.
     * - Empty string + nullable column => NULL
     * - "NULL" sentinel => NULL
     * - Empty string for non-string column => NULL
     */
    protected function coerce(array $column, mixed $value): mixed
    {
        if ($value === null) {
            return $column['nullable'] ? null : '';
        }
        if (is_array($value)) {
            $value = (string) ($value['value'] ?? '');
        }
        $type = strtolower($column['type']);
        $isString = (bool) preg_match('/char|text|enum|json|uuid|blob|binary/i', $type);

        if ($value === '' || $value === '__NULL__') {
            return $column['nullable'] ? null : ($isString ? '' : null);
        }
        return $value;
    }

    /**
     * Filter posted payload so only real columns are kept. Auto-increment
     * empty values are dropped so the DB can assign them.
     */
    protected function preparePayload(array $columns, array $input): array
    {
        $payload = [];
        foreach ($columns as $c) {
            if (! array_key_exists($c['name'], $input)) continue;
            $val = $this->coerce($c, $input[$c['name']]);
            $isAutoInc = stripos((string) ($c['extra'] ?? ''), 'auto_increment') !== false;
            if ($isAutoInc && ($val === null || $val === '')) {
                continue;
            }
            $payload[$c['name']] = $val;
        }
        return $payload;
    }

    /**
     * SELECT the current row before mutating it, so we can record the
     * pre-image in the activity log.  Returns null on any failure.
     */
    protected function fetchRow(string $connection, string $table, array $pkValues): ?array
    {
        if (! $this->logger()->isEnabled() || ! config('dblens.activity_log.capture_old_values', true)) {
            return null;
        }
        try {
            $driver = $this->cm->driver($connection);
            [$where, $bindings] = PkClause::build($driver, $pkValues);
            $sql = 'SELECT * FROM ' . $driver->quoteIdentifier($table)
                . ' WHERE ' . $where . ' LIMIT 1';
            $row = $driver->connection()->selectOne($sql, $bindings);
            return $row ? (array) $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function insert(string $connection, string $table, array $input): array
    {
        $this->assertWritable();
        $driver = $this->cm->driver($connection);
        $columns = $this->schema->columns($connection, $table);
        $payload = $this->preparePayload($columns, $input);
        if (empty($payload)) {
            throw new RuntimeException('No values provided.');
        }
        $payload = $this->applyHashedCasts($table, $payload);
        $cols = array_map(fn ($c) => $driver->quoteIdentifier($c), array_keys($payload));
        $placeholders = array_fill(0, count($payload), '?');
        $sql = 'INSERT INTO ' . $driver->quoteIdentifier($table)
            . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $driver->connection()->insert($sql, array_values($payload));

        // Try to return the PK of the inserted row
        $pk = $this->schema->primaryKey($connection, $table);
        $pkValues = [];
        if (count($pk) === 1 && ! isset($payload[$pk[0]])) {
            $id = $driver->connection()->getPdo()->lastInsertId();
            if ($id) $pkValues[$pk[0]] = $id;
        } else {
            foreach ($pk as $c) {
                if (array_key_exists($c, $payload)) $pkValues[$c] = $payload[$c];
            }
        }

        $this->logger()->log('row_created', "Inserted row into {$table}", [
            'connection' => $connection,
            'table' => $table,
            'pk' => $pkValues,
            'attributes' => $payload,
        ]);

        return $pkValues;
    }

    public function update(string $connection, string $table, array $pkValues, array $input): int
    {
        $this->assertWritable();
        if (empty($pkValues)) throw new RuntimeException('Cannot update: no primary key.');
        $driver = $this->cm->driver($connection);
        $columns = $this->schema->columns($connection, $table);
        $pkCols = array_keys($pkValues);

        $payload = $this->preparePayload($columns, $input);
        // Don't touch PK columns in SET unless user explicitly changed them.
        foreach ($pkCols as $c) {
            if (array_key_exists($c, $payload) && (string) $payload[$c] === (string) $pkValues[$c]) {
                unset($payload[$c]);
            }
        }
        if (empty($payload)) {
            return 0;
        }

        $payload = $this->applyHashedCasts($table, $payload);
        $oldRow = $this->fetchRow($connection, $table, $pkValues);

        $sets = [];
        $bindings = [];
        foreach ($payload as $col => $val) {
            $sets[] = $driver->quoteIdentifier($col) . ' = ?';
            $bindings[] = $val;
        }
        [$whereSql, $pkBindings] = PkClause::build($driver, $pkValues);
        $sql = 'UPDATE ' . $driver->quoteIdentifier($table)
            . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . $whereSql;
        $affected = $driver->connection()->update($sql, array_merge($bindings, $pkBindings));

        if ($affected > 0) {
            // Spatie convention: only the columns that actually changed end up
            // in the log, mirrored across `attributes` (new) and `old`.
            $changedNew = [];
            $changedOld = [];
            foreach ($payload as $col => $newVal) {
                $oldVal = $oldRow[$col] ?? null;
                if ((string) $oldVal !== (string) $newVal) {
                    $changedNew[$col] = $newVal;
                    $changedOld[$col] = $oldVal;
                }
            }
            if (! empty($changedNew)) {
                $this->logger()->log('row_updated', "Updated row in {$table}", [
                    'connection' => $connection,
                    'table' => $table,
                    'pk' => $pkValues,
                    'attributes' => $changedNew,
                    'old' => $changedOld,
                    'affected' => $affected,
                ]);
            }
        }
        return $affected;
    }

    /**
     * Delete a single row. $fkMode controls foreign-key handling:
     *   'none'           — plain DELETE (errors if referenced elsewhere).
     *   'disable_checks' — turn FK constraint checks off for the delete; rows
     *                      in other tables that referenced it are orphaned.
     *   'cascade'        — first delete rows in other tables that reference
     *                      THIS row (recursively), then the row itself.
     */
    public function delete(string $connection, string $table, array $pkValues, string $fkMode = 'none'): int
    {
        $this->assertWritable();
        if (empty($pkValues)) throw new RuntimeException('Cannot delete: no primary key.');
        $driver = $this->cm->driver($connection);
        $conn = $driver->connection();

        $oldRow = $this->fetchRow($connection, $table, $pkValues);

        [$where, $bindings] = PkClause::build($driver, $pkValues);
        $sql = 'DELETE FROM ' . $driver->quoteIdentifier($table) . ' WHERE ' . $where;

        $related = [];
        if ($fkMode === 'cascade') {
            $affected = (int) $conn->transaction(function () use ($connection, $table, $pkValues, $sql, $bindings, $conn, &$related) {
                foreach ($this->schema->incomingForeignKeys($connection, $table) as $fk) {
                    $child = $fk['table'] ?? null;
                    if (! $child || $child === $table) continue;
                    // The value referenced by the child rows — read straight from
                    // the row (independent of activity logging / capture_old_values).
                    $refVal = $conn->table($table)->where($pkValues)->value($fk['foreign_column']);
                    if ($refVal === null) continue;
                    $n = $this->deleteWhereCascade($connection, $child, $fk['column'], $refVal, [$table]);
                    if ($n > 0) $related[$child] = ($related[$child] ?? 0) + $n;
                }
                return $conn->delete($sql, $bindings);
            });
        } elseif ($fkMode === 'disable_checks') {
            $affected = (int) $conn->getSchemaBuilder()->withoutForeignKeyConstraints(fn () => $conn->delete($sql, $bindings));
        } else {
            $affected = (int) $conn->delete($sql, $bindings);
        }

        if ($affected > 0) {
            $this->logger()->log('row_deleted', "Deleted row from {$table}", array_merge([
                'connection' => $connection,
                'table' => $table,
                'pk' => $pkValues,
                'old' => $oldRow,
                'affected' => $affected,
                'fk_mode' => $fkMode,
            ], $related ? ['related' => $related] : []));
        }
        return $affected;
    }

    /**
     * Recursively delete rows in $table where $column = $value, deleting any
     * rows that reference them first. $stack guards against FK cycles.
     */
    protected function deleteWhereCascade(string $connection, string $table, string $column, mixed $value, array $stack): int
    {
        $conn = $this->cm->driver($connection)->connection();

        if (! in_array($table, $stack, true)) {
            foreach ($this->schema->incomingForeignKeys($connection, $table) as $fk) {
                $child = $fk['table'] ?? null;
                if (! $child || $child === $table) continue;
                $refVals = $conn->table($table)->where($column, $value)->distinct()->pluck($fk['foreign_column'])->all();
                foreach ($refVals as $rv) {
                    if ($rv === null) continue;
                    $this->deleteWhereCascade($connection, $child, $fk['column'], $rv, array_merge($stack, [$table]));
                }
            }
        }

        return (int) $conn->table($table)->where($column, $value)->delete();
    }

    /**
     * Bulk delete using a list of PK value sets. Wrapped in a transaction.
     * @param  array<int,array<string,mixed>>  $pkSets
     */
    public function bulkDelete(string $connection, string $table, array $pkSets, string $fkMode = 'none'): int
    {
        $this->assertWritable();
        $driver = $this->cm->driver($connection);
        $conn = $driver->connection();
        $deleted = 0;
        // disable_checks is applied once around the whole loop; cascade is
        // handled per-row by ::delete(); 'none' deletes plainly.
        $perRow = $fkMode === 'cascade' ? 'cascade' : 'none';
        $loop = function () use (&$deleted, $pkSets, $connection, $table, $perRow) {
            foreach ($pkSets as $pk) {
                if (!is_array($pk) || empty($pk)) continue;
                $deleted += $this->delete($connection, $table, $pk, $perRow);
            }
        };

        $conn->beginTransaction();
        try {
            if ($fkMode === 'disable_checks') {
                $conn->getSchemaBuilder()->withoutForeignKeyConstraints($loop);
            } else {
                $loop();
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        // Single aggregate entry alongside the per-row entries already logged
        // by ::delete() — useful for "bulk operation" auditing without losing
        // detail.
        $this->logger()->log('row_bulk_deleted', "Bulk-deleted {$deleted} row(s) from {$table}", [
            'connection' => $connection,
            'table' => $table,
            'count' => $deleted,
            'fk_mode' => $fkMode,
            'pk_sample' => array_slice($pkSets, 0, 10),
        ]);

        return $deleted;
    }

    /**
     * Soft-delete a set of rows: set the soft-delete column to NOW() instead
     * of removing them. Mirrors the single-row soft delete (cell update) for
     * each selected row, wrapped in one transaction.
     *
     * @param  array<int,array<string,mixed>>  $pkSets
     */
    public function bulkSoftDelete(string $connection, string $table, array $pkSets, string $column = 'deleted_at'): int
    {
        $this->assertWritable();
        $driver = $this->cm->driver($connection);
        $now = now()->format('Y-m-d H:i:s');
        $affected = 0;
        $driver->connection()->beginTransaction();
        try {
            foreach ($pkSets as $pk) {
                if (!is_array($pk) || empty($pk)) continue;
                $affected += $this->update($connection, $table, $pk, [$column => $now]);
            }
            $driver->connection()->commit();
        } catch (\Throwable $e) {
            $driver->connection()->rollBack();
            throw $e;
        }

        $this->logger()->log('row_bulk_soft_deleted', "Soft-deleted {$affected} row(s) in {$table}", [
            'connection' => $connection,
            'table' => $table,
            'column' => $column,
            'count' => $affected,
            'pk_sample' => array_slice($pkSets, 0, 10),
        ]);

        return $affected;
    }
}
