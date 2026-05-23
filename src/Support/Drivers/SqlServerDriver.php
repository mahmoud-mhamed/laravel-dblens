<?php

namespace MahmoudMhamed\DbLens\Support\Drivers;

use Illuminate\Database\Connection;

/**
 * Microsoft SQL Server driver.
 *
 * Implements the same surface as MySqlDriver / PgsqlDriver against the
 * INFORMATION_SCHEMA and sys.* catalog views. Some advanced features
 * (events, MySQL-style routine definitions, in-place RENAME COLUMN with
 * full re-spec) are emulated or marked as no-ops where SQL Server has no
 * direct equivalent.
 */
class SqlServerDriver implements DriverInterface
{
    public function __construct(protected Connection $conn) {}

    public function connection(): Connection { return $this->conn; }

    public function name(): string { return 'sqlsrv'; }

    public function quoteIdentifier(string $ident): string
    {
        return '[' . str_replace([']', '['], [']]', '['], $ident) . ']';
    }

    public function castToText(string $expression): string
    {
        return "CAST({$expression} AS NVARCHAR(MAX))";
    }

    protected function db(): string { return (string) $this->conn->getDatabaseName(); }

    protected function schema(): string
    {
        $cfg = $this->conn->getConfig('schema');
        if (is_array($cfg)) { $cfg = $cfg[0] ?? 'dbo'; }
        return (string) ($cfg ?: 'dbo');
    }

    public function tables(): array
    {
        $rows = $this->conn->select(
            "SELECT t.name AS [name],
                    p.rows AS [rows],
                    (SUM(a.total_pages) * 8 * 1024) AS [size],
                    NULL AS [engine],
                    NULL AS [collation],
                    CAST(ep.value AS NVARCHAR(MAX)) AS [comment]
             FROM sys.tables t
             INNER JOIN sys.indexes i ON i.object_id = t.object_id AND i.index_id IN (0,1)
             INNER JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id = i.index_id
             INNER JOIN sys.allocation_units a ON a.container_id = p.partition_id
             INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
             LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id AND ep.minor_id = 0 AND ep.name = 'MS_Description'
             WHERE s.name = ?
             GROUP BY t.name, p.rows, ep.value
             ORDER BY t.name",
            [$this->schema()]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'rows' => (int) ($r->rows ?? 0),
            'size' => (int) ($r->size ?? 0),
            'engine' => null,
            'collation' => null,
            'comment' => $r->comment ?? null,
        ], $rows);
    }

    public function tableInfo(string $table): array
    {
        $r = $this->conn->selectOne(
            "SELECT p.rows AS [rows],
                    (SUM(a.total_pages) * 8 * 1024) AS [size],
                    CAST(ep.value AS NVARCHAR(MAX)) AS [comment]
             FROM sys.tables t
             INNER JOIN sys.indexes i ON i.object_id = t.object_id AND i.index_id IN (0,1)
             INNER JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id = i.index_id
             INNER JOIN sys.allocation_units a ON a.container_id = p.partition_id
             INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
             LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id AND ep.minor_id = 0 AND ep.name = 'MS_Description'
             WHERE s.name = ? AND t.name = ?
             GROUP BY p.rows, ep.value",
            [$this->schema(), $table]
        );
        return [
            'rows' => (int) ($r->rows ?? 0),
            'size' => (int) ($r->size ?? 0),
            'engine' => null,
            'collation' => null,
            'comment' => $r->comment ?? null,
            'auto_increment' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    public function columns(string $table): array
    {
        $rows = $this->conn->select(
            "SELECT COLUMN_NAME AS name,
                    DATA_TYPE +
                        CASE WHEN CHARACTER_MAXIMUM_LENGTH IS NOT NULL THEN '(' + CAST(CHARACTER_MAXIMUM_LENGTH AS NVARCHAR(10)) + ')' ELSE '' END
                        AS type,
                    IS_NULLABLE AS nullable,
                    COLUMN_DEFAULT AS [default],
                    NULL AS [key],
                    NULL AS extra,
                    NULL AS comment
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION",
            [$this->schema(), $table]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'type' => (string) $r->type,
            'nullable' => strtoupper((string) $r->nullable) === 'YES',
            'default' => $r->default,
            'key' => $r->key ?: null,
            'extra' => $r->extra ?: null,
            'comment' => $r->comment ?: null,
        ], $rows);
    }

    public function indexes(string $table): array
    {
        $rows = $this->conn->select(
            "SELECT i.name AS name, c.name AS column_name,
                    CAST(i.is_unique AS INT) AS is_unique,
                    CAST(i.is_primary_key AS INT) AS is_primary,
                    ic.key_ordinal AS seq
             FROM sys.indexes i
             INNER JOIN sys.tables t ON t.object_id = i.object_id
             INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
             INNER JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
             INNER JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
             WHERE s.name = ? AND t.name = ? AND i.name IS NOT NULL
             ORDER BY i.name, ic.key_ordinal",
            [$this->schema(), $table]
        );
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->name]['name'] = $r->name;
            $grouped[$r->name]['unique'] = (bool) $r->is_unique;
            $grouped[$r->name]['primary'] = (bool) $r->is_primary;
            $grouped[$r->name]['columns'][] = (string) $r->column_name;
        }
        return array_values($grouped);
    }

    public function foreignKeys(string $table): array
    {
        $rows = $this->conn->select(
            "SELECT fk.name AS name,
                    pc.name AS [column],
                    rt.name AS foreign_table,
                    rc.name AS foreign_column,
                    fk.update_referential_action_desc AS on_update,
                    fk.delete_referential_action_desc AS on_delete
             FROM sys.foreign_keys fk
             INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
             INNER JOIN sys.tables pt ON pt.object_id = fk.parent_object_id
             INNER JOIN sys.schemas ps ON ps.schema_id = pt.schema_id
             INNER JOIN sys.columns pc ON pc.object_id = pt.object_id AND pc.column_id = fkc.parent_column_id
             INNER JOIN sys.tables rt ON rt.object_id = fk.referenced_object_id
             INNER JOIN sys.columns rc ON rc.object_id = rt.object_id AND rc.column_id = fkc.referenced_column_id
             WHERE ps.name = ? AND pt.name = ?",
            [$this->schema(), $table]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'column' => (string) $r->column,
            'foreign_table' => (string) $r->foreign_table,
            'foreign_column' => (string) $r->foreign_column,
            'on_update' => $r->on_update ?? null,
            'on_delete' => $r->on_delete ?? null,
        ], $rows);
    }

    public function incomingForeignKeys(string $table): array
    {
        $rows = $this->conn->select(
            "SELECT fk.name AS name,
                    pt.name AS [table],
                    pc.name AS [column],
                    rc.name AS foreign_column
             FROM sys.foreign_keys fk
             INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
             INNER JOIN sys.tables pt ON pt.object_id = fk.parent_object_id
             INNER JOIN sys.columns pc ON pc.object_id = pt.object_id AND pc.column_id = fkc.parent_column_id
             INNER JOIN sys.tables rt ON rt.object_id = fk.referenced_object_id
             INNER JOIN sys.schemas rs ON rs.schema_id = rt.schema_id
             INNER JOIN sys.columns rc ON rc.object_id = rt.object_id AND rc.column_id = fkc.referenced_column_id
             WHERE rs.name = ? AND rt.name = ?",
            [$this->schema(), $table]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'table' => (string) $r->table,
            'column' => (string) $r->column,
            'foreign_column' => (string) $r->foreign_column,
        ], $rows);
    }

    public function primaryKey(string $table): array
    {
        $rows = $this->conn->select(
            "SELECT c.name AS name
             FROM sys.indexes i
             INNER JOIN sys.tables t ON t.object_id = i.object_id
             INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
             INNER JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
             INNER JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
             WHERE i.is_primary_key = 1 AND s.name = ? AND t.name = ?
             ORDER BY ic.key_ordinal",
            [$this->schema(), $table]
        );
        return array_map(fn ($r) => (string) $r->name, $rows);
    }

    public function approximateRowCount(string $table): ?int
    {
        $r = $this->conn->selectOne(
            "SELECT SUM(p.rows) AS c
             FROM sys.tables t
             INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
             INNER JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0,1)
             WHERE s.name = ? AND t.name = ?",
            [$this->schema(), $table]
        );
        return $r && $r->c !== null ? (int) $r->c : null;
    }

    public function dropForeignKey(string $table, string $fkName): void
    {
        $this->conn->statement("ALTER TABLE {$this->qualified($table)} DROP CONSTRAINT {$this->quoteIdentifier($fkName)}");
    }

    protected function qualified(string $table): string
    {
        return $this->quoteIdentifier($this->schema()) . '.' . $this->quoteIdentifier($table);
    }

    protected function columnSpec(array $def): string
    {
        $sql = (string) $def['type'];
        $sql .= (! empty($def['nullable']) ? ' NULL' : ' NOT NULL');
        if (array_key_exists('default', $def) && $def['default'] !== null && $def['default'] !== '') {
            $d = $def['default'];
            if (preg_match('/^(GETDATE\(\)|SYSDATETIME\(\)|NULL)$/i', $d) || is_numeric($d)) {
                $sql .= ' DEFAULT ' . $d;
            } else {
                $sql .= ' DEFAULT ' . $this->conn->getPdo()->quote($d);
            }
        }
        return $sql;
    }

    public function addColumn(string $table, string $column, array $def): void
    {
        $this->conn->statement("ALTER TABLE {$this->qualified($table)} ADD {$this->quoteIdentifier($column)} {$this->columnSpec($def)}");
    }

    public function modifyColumn(string $table, string $column, array $def): void
    {
        $this->conn->statement("ALTER TABLE {$this->qualified($table)} ALTER COLUMN {$this->quoteIdentifier($column)} {$this->columnSpec($def)}");
    }

    public function renameColumn(string $table, string $from, string $to): void
    {
        $name = $this->schema() . '.' . $table . '.' . $from;
        $this->conn->statement("EXEC sp_rename ?, ?, 'COLUMN'", [$name, $to]);
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->conn->statement("ALTER TABLE {$this->qualified($table)} DROP COLUMN {$this->quoteIdentifier($column)}");
    }

    public function addIndex(string $table, string $name, array $columns, bool $unique = false): void
    {
        $cols = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $columns));
        $kw = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $this->conn->statement("CREATE {$kw} {$this->quoteIdentifier($name)} ON {$this->qualified($table)} ({$cols})");
    }

    public function dropIndex(string $table, string $name): void
    {
        $this->conn->statement("DROP INDEX {$this->quoteIdentifier($name)} ON {$this->qualified($table)}");
    }

    public function addForeignKey(string $table, string $name, string $column, string $refTable, string $refColumn, ?string $onUpdate = null, ?string $onDelete = null): void
    {
        $sql = "ALTER TABLE {$this->qualified($table)} ADD CONSTRAINT {$this->quoteIdentifier($name)} "
            . "FOREIGN KEY ({$this->quoteIdentifier($column)}) REFERENCES {$this->qualified($refTable)} ({$this->quoteIdentifier($refColumn)})";
        if ($onUpdate) $sql .= " ON UPDATE {$onUpdate}";
        if ($onDelete) $sql .= " ON DELETE {$onDelete}";
        $this->conn->statement($sql);
    }

    public function truncateTable(string $table): void
    {
        $this->conn->statement("TRUNCATE TABLE {$this->qualified($table)}");
    }

    public function dropTable(string $table): void
    {
        $this->conn->statement("DROP TABLE {$this->qualified($table)}");
    }

    public function renameTable(string $from, string $to): void
    {
        $old = $this->schema() . '.' . $from;
        $this->conn->statement("EXEC sp_rename ?, ?", [$old, $to]);
    }

    public function createTable(string $name, array $columns): void
    {
        $cols = [];
        $pks = [];
        foreach ($columns as $c) {
            $type = $c['type'];
            if (! empty($c['auto_increment'])) {
                $type = 'INT IDENTITY(1,1)';
            }
            $line = $this->quoteIdentifier($c['name']) . ' ' . $type;
            $line .= (! empty($c['nullable']) ? ' NULL' : ' NOT NULL');
            if (empty($c['auto_increment']) && array_key_exists('default', $c) && $c['default'] !== null && $c['default'] !== '') {
                $d = $c['default'];
                if (preg_match('/^(GETDATE\(\)|SYSDATETIME\(\)|NULL)$/i', $d) || is_numeric($d)) {
                    $line .= ' DEFAULT ' . $d;
                } else {
                    $line .= ' DEFAULT ' . $this->conn->getPdo()->quote($d);
                }
            }
            $cols[] = $line;
            if (! empty($c['primary'])) $pks[] = $this->quoteIdentifier($c['name']);
        }
        if (! empty($pks)) {
            $cols[] = 'PRIMARY KEY (' . implode(', ', $pks) . ')';
        }
        $sql = "CREATE TABLE {$this->qualified($name)} (\n  " . implode(",\n  ", $cols) . "\n)";
        $this->conn->statement($sql);
    }

    public function createTableSql(string $table): ?string
    {
        return null;
    }

    public function allColumns(): array
    {
        $out = [];
        foreach ($this->tables() as $t) {
            $out[$t['name']] = array_map(fn ($c) => [
                'name' => $c['name'],
                'type' => $c['type'],
                'nullable' => $c['nullable'],
                'key' => $c['key'],
            ], $this->columns($t['name']));
        }
        return $out;
    }

    public function allForeignKeys(): array
    {
        $out = [];
        foreach ($this->tables() as $t) {
            foreach ($this->foreignKeys($t['name']) as $fk) {
                $out[] = [
                    'table' => $t['name'],
                    'name' => $fk['name'],
                    'column' => $fk['column'],
                    'foreign_table' => $fk['foreign_table'],
                    'foreign_column' => $fk['foreign_column'],
                ];
            }
        }
        return $out;
    }

    public function views(): array
    {
        $rows = $this->conn->select(
            "SELECT v.name AS name, m.definition AS definition
             FROM sys.views v
             INNER JOIN sys.schemas s ON s.schema_id = v.schema_id
             LEFT JOIN sys.sql_modules m ON m.object_id = v.object_id
             WHERE s.name = ? ORDER BY v.name",
            [$this->schema()]
        );
        return array_map(fn ($r) => ['name' => (string) $r->name, 'definition' => $r->definition ?? null], $rows);
    }

    public function routines(): array
    {
        $rows = $this->conn->select(
            "SELECT o.name AS name,
                    CASE o.type WHEN 'P' THEN 'PROCEDURE' ELSE 'FUNCTION' END AS type,
                    m.definition AS definition
             FROM sys.objects o
             INNER JOIN sys.schemas s ON s.schema_id = o.schema_id
             LEFT JOIN sys.sql_modules m ON m.object_id = o.object_id
             WHERE s.name = ? AND o.type IN ('P','FN','IF','TF') ORDER BY o.name",
            [$this->schema()]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'type' => (string) $r->type,
            'definition' => $r->definition ?? null,
        ], $rows);
    }

    public function triggers(): array
    {
        $rows = $this->conn->select(
            "SELECT tr.name AS name,
                    t.name AS [table],
                    NULL AS event,
                    NULL AS timing,
                    m.definition AS definition
             FROM sys.triggers tr
             INNER JOIN sys.tables t ON t.object_id = tr.parent_id
             INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
             LEFT JOIN sys.sql_modules m ON m.object_id = tr.object_id
             WHERE s.name = ?",
            [$this->schema()]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'table' => (string) $r->table,
            'event' => '',
            'timing' => '',
            'definition' => $r->definition ?? null,
        ], $rows);
    }

    public function events(): array
    {
        return [];
    }

    public function dropView(string $name): void
    {
        $this->conn->statement('DROP VIEW ' . $this->qualified($name));
    }

    public function dropRoutine(string $name, string $type): void
    {
        $type = strtoupper($type) === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
        $this->conn->statement("DROP {$type} " . $this->qualified($name));
    }

    public function dropTrigger(string $name): void
    {
        $this->conn->statement('DROP TRIGGER ' . $this->quoteIdentifier($name));
    }

    public function dropEvent(string $name): void
    {
        throw new \RuntimeException('SQL Server has no events; use SQL Server Agent jobs.');
    }
}
