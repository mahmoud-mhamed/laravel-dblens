<?php

namespace MahmoudMhamed\DbLens\Support\Drivers;

use Illuminate\Database\Connection;

class MySqlDriver implements DriverInterface
{
    public function __construct(protected Connection $conn) {}

    public function connection(): Connection { return $this->conn; }

    public function name(): string { return 'mysql'; }

    public function quoteIdentifier(string $ident): string
    {
        return '`' . str_replace('`', '``', $ident) . '`';
    }

    public function castToText(string $expression): string
    {
        return "CAST({$expression} AS CHAR)";
    }

    protected function db(): string { return (string) $this->conn->getDatabaseName(); }

    public function tables(): array
    {
        $rows = $this->conn->select(
            'SELECT TABLE_NAME as `name`, TABLE_ROWS as `rows`, (DATA_LENGTH + INDEX_LENGTH) as `size`, ENGINE as `engine`, TABLE_COLLATION as `collation`, TABLE_COMMENT as `comment`
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?
             ORDER BY TABLE_NAME',
            [$this->db(), 'BASE TABLE']
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'rows' => (int) ($r->rows ?? 0),
            'size' => (int) ($r->size ?? 0),
            'engine' => $r->engine ?? null,
            'collation' => $r->collation ?? null,
            'comment' => $r->comment ?? null,
        ], $rows);
    }

    public function tableInfo(string $table): array
    {
        $r = $this->conn->selectOne(
            'SELECT TABLE_ROWS as `rows`, (DATA_LENGTH + INDEX_LENGTH) as `size`, ENGINE as `engine`, TABLE_COLLATION as `collation`, TABLE_COMMENT as `comment`, AUTO_INCREMENT as `auto_increment`, CREATE_TIME as `created_at`, UPDATE_TIME as `updated_at`
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->db(), $table]
        );
        return [
            'rows' => (int) ($r->rows ?? 0),
            'size' => (int) ($r->size ?? 0),
            'engine' => $r->engine ?? null,
            'collation' => $r->collation ?? null,
            'comment' => $r->comment ?? null,
            'auto_increment' => $r->auto_increment ? (int) $r->auto_increment : null,
            'created_at' => $r->created_at ?? null,
            'updated_at' => $r->updated_at ?? null,
        ];
    }

    public function columns(string $table): array
    {
        $rows = $this->conn->select(
            'SELECT COLUMN_NAME as name, COLUMN_TYPE as type, IS_NULLABLE as nullable, COLUMN_DEFAULT as `default`, COLUMN_KEY as `key`, EXTRA as extra, COLUMN_COMMENT as comment
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$this->db(), $table]
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
            'SELECT INDEX_NAME as name, COLUMN_NAME as column_name, NON_UNIQUE as non_unique, SEQ_IN_INDEX as seq
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$this->db(), $table]
        );
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->name]['name'] = $r->name;
            $grouped[$r->name]['unique'] = ! (bool) $r->non_unique;
            $grouped[$r->name]['primary'] = $r->name === 'PRIMARY';
            $grouped[$r->name]['columns'][] = (string) $r->column_name;
        }
        return array_values($grouped);
    }

    public function foreignKeys(string $table): array
    {
        $rows = $this->conn->select(
            'SELECT kcu.CONSTRAINT_NAME as name, kcu.COLUMN_NAME as `column`, kcu.REFERENCED_TABLE_NAME as foreign_table, kcu.REFERENCED_COLUMN_NAME as foreign_column,
                    rc.UPDATE_RULE as on_update, rc.DELETE_RULE as on_delete
             FROM information_schema.KEY_COLUMN_USAGE kcu
             LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ? AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION',
            [$this->db(), $table]
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
            'SELECT CONSTRAINT_NAME as name, TABLE_NAME as `table`, COLUMN_NAME as `column`, REFERENCED_COLUMN_NAME as foreign_column
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?
             ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION',
            [$this->db(), $table]
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
            'SELECT COLUMN_NAME as name FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [$this->db(), $table, 'PRIMARY']
        );
        return array_map(fn ($r) => (string) $r->name, $rows);
    }

    public function approximateRowCount(string $table): ?int
    {
        $r = $this->conn->selectOne(
            'SELECT TABLE_ROWS as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->db(), $table]
        );
        return $r && $r->c !== null ? (int) $r->c : null;
    }

    public function dropForeignKey(string $table, string $fkName): void
    {
        $t = $this->quoteIdentifier($table);
        $fk = $this->quoteIdentifier($fkName);
        $this->conn->statement("ALTER TABLE {$t} DROP FOREIGN KEY {$fk}");
    }

    protected function columnSpec(array $def): string
    {
        $type = (string) $def['type'];
        $sql = $type;
        $sql .= (! empty($def['nullable']) ? ' NULL' : ' NOT NULL');
        if (array_key_exists('default', $def) && $def['default'] !== null && $def['default'] !== '') {
            $d = $def['default'];
            if (preg_match('/^(CURRENT_TIMESTAMP|NULL|TRUE|FALSE)$/i', $d) || is_numeric($d)) {
                $sql .= ' DEFAULT ' . $d;
            } else {
                $sql .= ' DEFAULT ' . $this->conn->getPdo()->quote($d);
            }
        }
        if (! empty($def['comment'])) {
            $sql .= ' COMMENT ' . $this->conn->getPdo()->quote($def['comment']);
        }
        return $sql;
    }

    public function addColumn(string $table, string $column, array $def): void
    {
        $sql = "ALTER TABLE {$this->quoteIdentifier($table)} ADD COLUMN {$this->quoteIdentifier($column)} {$this->columnSpec($def)}";
        if (! empty($def['after'])) {
            $sql .= ' AFTER ' . $this->quoteIdentifier($def['after']);
        }
        $this->conn->statement($sql);
    }

    public function modifyColumn(string $table, string $column, array $def): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} MODIFY COLUMN {$this->quoteIdentifier($column)} {$this->columnSpec($def)}");
    }

    public function renameColumn(string $table, string $from, string $to): void
    {
        // `ALTER … RENAME COLUMN` only exists on MySQL 8.0+. On older
        // versions (and any branded MariaDB) we fall back to `CHANGE COLUMN`,
        // which requires re-stating the column's full definition.
        if ($this->supportsRenameColumn()) {
            $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} RENAME COLUMN {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}");
            return;
        }
        $cols = $this->columns($table);
        $def = null;
        foreach ($cols as $c) {
            if ($c['name'] === $from) { $def = $c; break; }
        }
        if ($def === null) {
            throw new \RuntimeException("Column [{$from}] not found on [{$table}].");
        }
        $spec = $this->columnSpec([
            'type' => $def['type'],
            'nullable' => $def['nullable'],
            'default' => $def['default'],
            'comment' => $def['comment'],
        ]);
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} CHANGE COLUMN {$this->quoteIdentifier($from)} {$this->quoteIdentifier($to)} {$spec}");
    }

    protected function supportsRenameColumn(): bool
    {
        try {
            $r = $this->conn->selectOne('SELECT VERSION() as v');
            $v = (string) ($r->v ?? '');
        } catch (\Throwable $e) {
            return true; // assume modern server on any error
        }
        // MariaDB also supports RENAME COLUMN, since 10.5.2.
        if (stripos($v, 'mariadb') !== false) {
            return version_compare(preg_replace('/[^\d.].*$/', '', $v), '10.5.2', '>=');
        }
        return version_compare(preg_replace('/[^\d.].*$/', '', $v), '8.0.0', '>=');
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} DROP COLUMN {$this->quoteIdentifier($column)}");
    }

    public function addIndex(string $table, string $name, array $columns, bool $unique = false): void
    {
        $cols = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $columns));
        $kw = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} ADD {$kw} {$this->quoteIdentifier($name)} ({$cols})");
    }

    public function dropIndex(string $table, string $name): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} DROP INDEX {$this->quoteIdentifier($name)}");
    }

    public function addForeignKey(string $table, string $name, string $column, string $refTable, string $refColumn, ?string $onUpdate = null, ?string $onDelete = null): void
    {
        $sql = "ALTER TABLE {$this->quoteIdentifier($table)} ADD CONSTRAINT {$this->quoteIdentifier($name)} "
            . "FOREIGN KEY ({$this->quoteIdentifier($column)}) REFERENCES {$this->quoteIdentifier($refTable)} ({$this->quoteIdentifier($refColumn)})";
        if ($onUpdate) $sql .= " ON UPDATE {$onUpdate}";
        if ($onDelete) $sql .= " ON DELETE {$onDelete}";
        $this->conn->statement($sql);
    }

    public function truncateTable(string $table): void
    {
        $this->conn->statement("TRUNCATE TABLE {$this->quoteIdentifier($table)}");
    }

    public function dropTable(string $table): void
    {
        $this->conn->statement("DROP TABLE {$this->quoteIdentifier($table)}");
    }

    public function renameTable(string $from, string $to): void
    {
        $this->conn->statement("RENAME TABLE {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}");
    }

    public function allColumns(): array
    {
        $rows = $this->conn->select(
            'SELECT TABLE_NAME as `table`, COLUMN_NAME as `name`, COLUMN_TYPE as `type`, IS_NULLABLE as `nullable`, COLUMN_KEY as `key`
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$this->db()]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r->table][] = [
                'name' => (string) $r->name,
                'type' => (string) $r->type,
                'nullable' => strtoupper((string) $r->nullable) === 'YES',
                'key' => $r->key ?: null,
            ];
        }
        return $out;
    }

    public function allForeignKeys(): array
    {
        $rows = $this->conn->select(
            'SELECT TABLE_NAME as `table`, CONSTRAINT_NAME as `name`, COLUMN_NAME as `column`, REFERENCED_TABLE_NAME as foreign_table, REFERENCED_COLUMN_NAME as foreign_column
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$this->db()]
        );
        return array_map(fn ($r) => [
            'table' => (string) $r->table,
            'name' => (string) $r->name,
            'column' => (string) $r->column,
            'foreign_table' => (string) $r->foreign_table,
            'foreign_column' => (string) $r->foreign_column,
        ], $rows);
    }

    public function createTableSql(string $table): ?string
    {
        $r = $this->conn->selectOne('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
        if (! $r) return null;
        $arr = (array) $r;
        return (string) ($arr['Create Table'] ?? array_values($arr)[1] ?? null);
    }

    public function createTable(string $name, array $columns): void
    {
        $cols = [];
        $pks = [];
        foreach ($columns as $c) {
            $line = $this->quoteIdentifier($c['name']) . ' ' . $c['type'];
            $line .= (! empty($c['nullable']) ? ' NULL' : ' NOT NULL');
            if (! empty($c['auto_increment'])) $line .= ' AUTO_INCREMENT';
            if (array_key_exists('default', $c) && $c['default'] !== null && $c['default'] !== '') {
                $d = $c['default'];
                if (preg_match('/^(CURRENT_TIMESTAMP|NULL|TRUE|FALSE)$/i', $d) || is_numeric($d)) {
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
        $sql = "CREATE TABLE {$this->quoteIdentifier($name)} (\n  " . implode(",\n  ", $cols) . "\n)";
        $this->conn->statement($sql);
    }

    public function views(): array
    {
        $rows = $this->conn->select(
            'SELECT TABLE_NAME as name, VIEW_DEFINITION as definition
             FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$this->db()]
        );
        return array_map(fn ($r) => ['name' => (string) $r->name, 'definition' => $r->definition ?? null], $rows);
    }

    public function routines(): array
    {
        $rows = $this->conn->select(
            'SELECT ROUTINE_NAME as name, ROUTINE_TYPE as type, ROUTINE_DEFINITION as definition
             FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? ORDER BY ROUTINE_TYPE, ROUTINE_NAME',
            [$this->db()]
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
            'SELECT TRIGGER_NAME as name, EVENT_OBJECT_TABLE as `table`, EVENT_MANIPULATION as event,
                    ACTION_TIMING as timing, ACTION_STATEMENT as definition
             FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME',
            [$this->db()]
        );
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'table' => (string) $r->table,
            'event' => (string) $r->event,
            'timing' => (string) $r->timing,
            'definition' => $r->definition ?? null,
        ], $rows);
    }

    public function events(): array
    {
        try {
            $rows = $this->conn->select(
                'SELECT EVENT_NAME as name, EVENT_DEFINITION as definition,
                        IFNULL(INTERVAL_VALUE, EXECUTE_AT) as `schedule`, STATUS as status
                 FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ? ORDER BY EVENT_NAME',
                [$this->db()]
            );
        } catch (\Throwable $e) {
            return [];
        }
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'definition' => $r->definition ?? null,
            'schedule' => $r->schedule ?? null,
            'status' => $r->status ?? null,
        ], $rows);
    }

    public function dropView(string $name): void
    {
        $this->conn->statement('DROP VIEW ' . $this->quoteIdentifier($name));
    }

    public function dropRoutine(string $name, string $type): void
    {
        $type = strtoupper($type) === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
        $this->conn->statement("DROP {$type} " . $this->quoteIdentifier($name));
    }

    public function dropTrigger(string $name): void
    {
        $this->conn->statement('DROP TRIGGER ' . $this->quoteIdentifier($name));
    }

    public function dropEvent(string $name): void
    {
        $this->conn->statement('DROP EVENT ' . $this->quoteIdentifier($name));
    }
}
