<?php

namespace MahmoudMhamed\DbLens\Support\Drivers;

use Illuminate\Database\Connection;

class SqliteDriver implements DriverInterface
{
    public function __construct(protected Connection $conn) {}

    public function connection(): Connection { return $this->conn; }

    public function name(): string { return 'sqlite'; }

    public function quoteIdentifier(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    public function castToText(string $expression): string
    {
        return "CAST({$expression} AS TEXT)";
    }

    public function tables(): array
    {
        $rows = $this->conn->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $out = [];
        foreach ($rows as $r) {
            $count = (int) ($this->conn->selectOne('SELECT COUNT(*) as c FROM ' . $this->quoteIdentifier($r->name))->c ?? 0);
            $out[] = [
                'name' => (string) $r->name,
                'rows' => $count,
                'size' => 0,
                'engine' => null,
                'collation' => null,
                'comment' => null,
            ];
        }
        return $out;
    }

    public function tableInfo(string $table): array
    {
        $count = (int) ($this->conn->selectOne('SELECT COUNT(*) as c FROM ' . $this->quoteIdentifier($table))->c ?? 0);
        return [
            'rows' => $count,
            'size' => 0,
            'engine' => null,
            'collation' => null,
            'comment' => null,
            'auto_increment' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    public function columns(string $table): array
    {
        $rows = $this->conn->select("PRAGMA table_info(" . $this->quoteIdentifier($table) . ")");
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'type' => (string) $r->type,
            'nullable' => ! (bool) $r->notnull,
            'default' => $r->dflt_value,
            'key' => $r->pk ? 'PRI' : null,
            'extra' => null,
            'comment' => null,
        ], $rows);
    }

    public function indexes(string $table): array
    {
        $idx = $this->conn->select("PRAGMA index_list(" . $this->quoteIdentifier($table) . ")");
        $out = [];
        foreach ($idx as $i) {
            $cols = $this->conn->select("PRAGMA index_info(" . $this->quoteIdentifier($i->name) . ")");
            $out[] = [
                'name' => (string) $i->name,
                'columns' => array_map(fn ($c) => (string) $c->name, $cols),
                'unique' => (bool) $i->unique,
                'primary' => ($i->origin ?? '') === 'pk',
            ];
        }
        return $out;
    }

    public function foreignKeys(string $table): array
    {
        $rows = $this->conn->select("PRAGMA foreign_key_list(" . $this->quoteIdentifier($table) . ")");
        return array_map(fn ($r) => [
            'name' => 'fk_' . $r->id,
            'column' => (string) $r->from,
            'foreign_table' => (string) $r->table,
            'foreign_column' => (string) $r->to,
            'on_update' => $r->on_update ?? null,
            'on_delete' => $r->on_delete ?? null,
        ], $rows);
    }

    public function incomingForeignKeys(string $table): array
    {
        $tables = $this->conn->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $out = [];
        foreach ($tables as $t) {
            $fks = $this->conn->select("PRAGMA foreign_key_list(" . $this->quoteIdentifier($t->name) . ")");
            foreach ($fks as $fk) {
                if ($fk->table === $table) {
                    $out[] = [
                        'name' => 'fk_' . $fk->id,
                        'table' => (string) $t->name,
                        'column' => (string) $fk->from,
                        'foreign_column' => (string) $fk->to,
                    ];
                }
            }
        }
        return $out;
    }

    public function primaryKey(string $table): array
    {
        $rows = $this->conn->select("PRAGMA table_info(" . $this->quoteIdentifier($table) . ")");
        $pk = [];
        foreach ($rows as $r) {
            if ($r->pk) $pk[] = (string) $r->name;
        }
        return $pk;
    }

    public function approximateRowCount(string $table): ?int
    {
        // SQLite has no cheap row-count estimate — let the caller fall back to COUNT(*).
        return null;
    }

    public function dropForeignKey(string $table, string $fkName): void
    {
        throw new \RuntimeException('SQLite does not support dropping foreign keys directly. Use a table rebuild.');
    }

    public function addColumn(string $table, string $column, array $def): void
    {
        $sql = "ALTER TABLE {$this->quoteIdentifier($table)} ADD COLUMN {$this->quoteIdentifier($column)} " . $def['type'];
        $sql .= (! empty($def['nullable']) ? '' : ' NOT NULL');
        if (array_key_exists('default', $def) && $def['default'] !== null && $def['default'] !== '') {
            $d = $def['default'];
            $sql .= ' DEFAULT ' . (is_numeric($d) || preg_match('/^(CURRENT_TIMESTAMP|NULL)$/i', $d) ? $d : $this->conn->getPdo()->quote($d));
        }
        $this->conn->statement($sql);
    }

    public function modifyColumn(string $table, string $column, array $def): void
    {
        throw new \RuntimeException('SQLite does not support MODIFY COLUMN.');
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
        throw new \RuntimeException('SQLite does not support adding foreign keys via ALTER. Add it during table creation.');
    }

    public function truncateTable(string $table): void
    {
        $this->conn->statement("DELETE FROM {$this->quoteIdentifier($table)}");
    }

    public function dropTable(string $table): void
    {
        $this->conn->statement("DROP TABLE {$this->quoteIdentifier($table)}");
    }

    public function renameTable(string $from, string $to): void
    {
        $this->conn->statement("ALTER TABLE {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}");
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

    public function createTableSql(string $table): ?string
    {
        $r = $this->conn->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        return $r ? (string) $r->sql : null;
    }

    public function createTable(string $name, array $columns): void
    {
        $cols = [];
        $pks = [];
        $hasAuto = false;
        foreach ($columns as $c) {
            $type = $c['type'];
            $line = $this->quoteIdentifier($c['name']) . ' ' . $type;
            if (! empty($c['auto_increment']) && ! empty($c['primary'])) {
                $line .= ' PRIMARY KEY AUTOINCREMENT';
                $hasAuto = true;
            } else {
                if (! empty($c['nullable']) === false) $line .= ' NOT NULL';
                if (array_key_exists('default', $c) && $c['default'] !== null && $c['default'] !== '') {
                    $d = $c['default'];
                    $line .= ' DEFAULT ' . (is_numeric($d) || preg_match('/^(CURRENT_TIMESTAMP|NULL)$/i', $d) ? $d : $this->conn->getPdo()->quote($d));
                }
                if (! empty($c['primary'])) $pks[] = $this->quoteIdentifier($c['name']);
            }
            $cols[] = $line;
        }
        if (! $hasAuto && ! empty($pks)) {
            $cols[] = 'PRIMARY KEY (' . implode(', ', $pks) . ')';
        }
        $sql = "CREATE TABLE {$this->quoteIdentifier($name)} (\n  " . implode(",\n  ", $cols) . "\n)";
        $this->conn->statement($sql);
    }

    public function views(): array
    {
        $rows = $this->conn->select("SELECT name, sql as definition FROM sqlite_master WHERE type = 'view' ORDER BY name");
        return array_map(fn ($r) => ['name' => (string) $r->name, 'definition' => $r->definition ?? null], $rows);
    }

    public function routines(): array { return []; }

    public function triggers(): array
    {
        $rows = $this->conn->select("SELECT name, tbl_name as `table`, sql as definition FROM sqlite_master WHERE type = 'trigger' ORDER BY name");
        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'table' => (string) $r->table,
            'event' => '',
            'timing' => '',
            'definition' => $r->definition ?? null,
        ], $rows);
    }

    public function events(): array { return []; }

    public function dropView(string $name): void
    {
        $this->conn->statement('DROP VIEW ' . $this->quoteIdentifier($name));
    }

    public function dropRoutine(string $name, string $type): void
    {
        throw new \RuntimeException('SQLite has no stored routines.');
    }

    public function dropTrigger(string $name): void
    {
        $this->conn->statement('DROP TRIGGER ' . $this->quoteIdentifier($name));
    }

    public function dropEvent(string $name): void
    {
        throw new \RuntimeException('SQLite has no events.');
    }
}
