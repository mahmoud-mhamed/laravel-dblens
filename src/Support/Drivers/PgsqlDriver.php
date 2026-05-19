<?php

namespace MahmoudMhamed\DbLens\Support\Drivers;

use Illuminate\Database\Connection;

class PgsqlDriver implements DriverInterface
{
    public function __construct(protected Connection $conn) {}

    public function connection(): Connection { return $this->conn; }

    public function name(): string { return 'pgsql'; }

    public function quoteIdentifier(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    protected function schema(): string
    {
        $cfg = $this->conn->getConfig('search_path') ?: $this->conn->getConfig('schema') ?: 'public';
        if (is_array($cfg)) { $cfg = $cfg[0] ?? 'public'; }
        return (string) $cfg;
    }

    public function tables(): array
    {
        $rows = $this->conn->select(
            "SELECT c.relname as name,
                    c.reltuples::bigint as rows,
                    pg_total_relation_size(c.oid) as size,
                    NULL as engine,
                    NULL as collation,
                    obj_description(c.oid) as comment
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = ? AND c.relkind = 'r'
             ORDER BY c.relname",
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
            "SELECT c.reltuples::bigint as rows,
                    pg_total_relation_size(c.oid) as size,
                    obj_description(c.oid) as comment
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = ? AND c.relname = ?",
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
            "SELECT column_name as name, udt_name || (CASE WHEN character_maximum_length IS NOT NULL THEN '('||character_maximum_length||')' ELSE '' END) as type,
                    is_nullable as nullable, column_default as \"default\",
                    NULL as key, NULL as extra,
                    col_description((table_schema||'.'||table_name)::regclass, ordinal_position) as comment
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position",
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
            "SELECT i.relname as name, a.attname as column_name, ix.indisunique as is_unique, ix.indisprimary as is_primary,
                    array_position(ix.indkey, a.attnum) as seq
             FROM pg_class t
             JOIN pg_namespace n ON n.oid = t.relnamespace
             JOIN pg_index ix ON ix.indrelid = t.oid
             JOIN pg_class i ON i.oid = ix.indexrelid
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
             WHERE n.nspname = ? AND t.relname = ?
             ORDER BY i.relname, seq",
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
            "SELECT con.conname as name,
                    att.attname as \"column\",
                    cl_f.relname as foreign_table,
                    att_f.attname as foreign_column,
                    con.confupdtype as on_update,
                    con.confdeltype as on_delete
             FROM pg_constraint con
             JOIN pg_class cl ON cl.oid = con.conrelid
             JOIN pg_namespace ns ON ns.oid = cl.relnamespace
             JOIN pg_class cl_f ON cl_f.oid = con.confrelid
             JOIN pg_attribute att ON att.attrelid = cl.oid AND att.attnum = ANY(con.conkey)
             JOIN pg_attribute att_f ON att_f.attrelid = cl_f.oid AND att_f.attnum = ANY(con.confkey)
             WHERE con.contype = 'f' AND ns.nspname = ? AND cl.relname = ?
             ORDER BY con.conname",
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
            "SELECT con.conname as name,
                    cl.relname as \"table\",
                    att.attname as \"column\",
                    att_f.attname as foreign_column
             FROM pg_constraint con
             JOIN pg_class cl ON cl.oid = con.conrelid
             JOIN pg_namespace ns ON ns.oid = cl.relnamespace
             JOIN pg_class cl_f ON cl_f.oid = con.confrelid
             JOIN pg_attribute att ON att.attrelid = cl.oid AND att.attnum = ANY(con.conkey)
             JOIN pg_attribute att_f ON att_f.attrelid = cl_f.oid AND att_f.attnum = ANY(con.confkey)
             WHERE con.contype = 'f' AND ns.nspname = ? AND cl_f.relname = ?",
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
            "SELECT a.attname as name
             FROM pg_index ix
             JOIN pg_class c ON c.oid = ix.indrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(ix.indkey)
             WHERE ix.indisprimary AND n.nspname = ? AND c.relname = ?",
            [$this->schema(), $table]
        );
        return array_map(fn ($r) => (string) $r->name, $rows);
    }

    public function dropForeignKey(string $table, string $fkName): void
    {
        $t = $this->quoteIdentifier($table);
        $fk = $this->quoteIdentifier($fkName);
        $this->conn->statement("ALTER TABLE {$t} DROP CONSTRAINT {$fk}");
    }

    protected function columnSpec(array $def): string
    {
        $sql = (string) $def['type'];
        $sql .= (! empty($def['nullable']) ? '' : ' NOT NULL');
        if (array_key_exists('default', $def) && $def['default'] !== null && $def['default'] !== '') {
            $d = $def['default'];
            if (preg_match('/^(CURRENT_TIMESTAMP|NOW\(\)|NULL|TRUE|FALSE)$/i', $d) || is_numeric($d)) {
                $sql .= ' DEFAULT ' . $d;
            } else {
                $sql .= ' DEFAULT ' . $this->conn->getPdo()->quote($d);
            }
        }
        return $sql;
    }

    public function addColumn(string $table, string $column, array $def): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} ADD COLUMN {$this->quoteIdentifier($column)} {$this->columnSpec($def)}");
        if (! empty($def['comment'])) {
            $this->conn->statement("COMMENT ON COLUMN {$this->quoteIdentifier($table)}.{$this->quoteIdentifier($column)} IS " . $this->conn->getPdo()->quote($def['comment']));
        }
    }

    public function modifyColumn(string $table, string $column, array $def): void
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($column);
        $this->conn->statement("ALTER TABLE {$t} ALTER COLUMN {$c} TYPE " . $def['type']);
        $this->conn->statement("ALTER TABLE {$t} ALTER COLUMN {$c} " . (empty($def['nullable']) ? 'SET NOT NULL' : 'DROP NOT NULL'));
        if (array_key_exists('default', $def)) {
            if ($def['default'] === null || $def['default'] === '') {
                $this->conn->statement("ALTER TABLE {$t} ALTER COLUMN {$c} DROP DEFAULT");
            } else {
                $d = $def['default'];
                $dQuoted = (preg_match('/^(CURRENT_TIMESTAMP|NOW\(\)|NULL|TRUE|FALSE)$/i', $d) || is_numeric($d)) ? $d : $this->conn->getPdo()->quote($d);
                $this->conn->statement("ALTER TABLE {$t} ALTER COLUMN {$c} SET DEFAULT {$dQuoted}");
            }
        }
    }

    public function renameColumn(string $table, string $from, string $to): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} RENAME COLUMN {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}");
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($table)} DROP COLUMN {$this->quoteIdentifier($column)}");
    }

    public function addIndex(string $table, string $name, array $columns, bool $unique = false): void
    {
        $cols = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $columns));
        $kw = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $this->conn->statement("CREATE {$kw} {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} ({$cols})");
    }

    public function dropIndex(string $table, string $name): void
    {
        $this->conn->statement("DROP INDEX {$this->quoteIdentifier($name)}");
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
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}");
    }

    public function createTableSql(string $table): ?string
    {
        return null; // pg_dump-style reconstruction is out of scope; export data only.
    }

    public function createTable(string $name, array $columns): void
    {
        $cols = [];
        $pks = [];
        foreach ($columns as $c) {
            $type = $c['type'];
            if (! empty($c['auto_increment'])) {
                $type = stripos($type, 'bigint') !== false ? 'BIGSERIAL' : 'SERIAL';
            }
            $line = $this->quoteIdentifier($c['name']) . ' ' . $type;
            $line .= (! empty($c['nullable']) ? '' : ' NOT NULL');
            if (empty($c['auto_increment']) && array_key_exists('default', $c) && $c['default'] !== null && $c['default'] !== '') {
                $d = $c['default'];
                if (preg_match('/^(CURRENT_TIMESTAMP|NOW\(\)|NULL|TRUE|FALSE)$/i', $d) || is_numeric($d)) {
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
}
