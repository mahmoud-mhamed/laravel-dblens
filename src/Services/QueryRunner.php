<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

class QueryRunner
{
    public function __construct(protected ConnectionManager $cm) {}

    /**
     * Browse a table with optional filters/search/sort/pagination.
     *
     * @param  array  $filters  list of ['column'=>..,'op'=>'=','value'=>..]
     * @return array{rows:array<int,array<string,mixed>>,paginator:LengthAwarePaginator,sql:string,total:int}
     */
    public function browse(string $connection, string $table, array $options = []): array
    {
        $driver = $this->cm->driver($connection);
        $conn = $driver->connection();
        $qt = $driver->quoteIdentifier($table);

        $perPage = (int) ($options['per_page'] ?? config('dblens.browse.per_page', 30));
        $perPage = max(1, min($perPage, 1000));
        $page = max(1, (int) ($options['page'] ?? 1));
        $orderBy = $options['order_by'] ?? null;
        $orderDir = strtoupper((string) ($options['order_dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $search = trim((string) ($options['search'] ?? ''));
        $filters = (array) ($options['filters'] ?? []);

        $columns = $driver->columns($table);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        [$whereSql, $bindings] = $this->buildWhere($driver, $columns, $filters, $search);

        $orderSql = '';
        if ($orderBy && in_array($orderBy, $columnNames, true)) {
            $orderSql = ' ORDER BY ' . $driver->quoteIdentifier($orderBy) . ' ' . $orderDir;
        } else {
            $pk = $driver->primaryKey($table);
            if (!empty($pk)) {
                $orderSql = ' ORDER BY ' . $driver->quoteIdentifier($pk[0]) . ' ASC';
            }
        }

        $totalSql = "SELECT COUNT(*) as c FROM {$qt}{$whereSql}";
        $total = (int) ($conn->selectOne($totalSql, $bindings)->c ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$qt}{$whereSql}{$orderSql} LIMIT {$perPage} OFFSET {$offset}";
        $rows = $conn->select($sql, $bindings);
        $rows = array_map(fn ($r) => (array) $r, $rows);

        $paginator = new LengthAwarePaginator(
            $rows, $total, $perPage, $page,
            ['path' => Request::url(), 'query' => Request::query()]
        );

        return ['rows' => $rows, 'paginator' => $paginator, 'sql' => $sql, 'total' => $total];
    }

    protected function buildWhere($driver, array $columns, array $filters, string $search): array
    {
        $conds = [];
        $bindings = [];
        $allowedOps = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL'];
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        foreach ($filters as $f) {
            if (! is_array($f)) continue;
            // Skip filters that are explicitly disabled (toggle off)
            if (array_key_exists('enabled', $f) && in_array($f['enabled'], ['0', 0, false, 'false'], true)) continue;
            $col = $f['column'] ?? null;
            $op = strtoupper((string) ($f['op'] ?? '='));
            $val = $f['value'] ?? null;
            if (! in_array($col, $columnNames, true)) continue;
            if (! in_array($op, $allowedOps, true)) continue;
            $qc = $driver->quoteIdentifier($col);
            if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                $conds[] = "{$qc} {$op}";
            } elseif ($op === 'LIKE' || $op === 'NOT LIKE') {
                $conds[] = "{$qc} {$op} ?";
                $bindings[] = $val;
            } else {
                $conds[] = "{$qc} {$op} ?";
                $bindings[] = $val;
            }
        }

        if ($search !== '') {
            $searchable = [];
            foreach ($columns as $c) {
                $type = strtolower($c['type']);
                if (preg_match('/char|text|enum|json|uuid/i', $type) || preg_match('/^varchar|^text|^longtext|^mediumtext|^tinytext/i', $type)) {
                    $searchable[] = $c['name'];
                }
            }
            // also include int/numeric so users can find by id
            foreach ($columns as $c) {
                if (preg_match('/int|numeric|decimal|float|double/i', $c['type']) && ! in_array($c['name'], $searchable, true)) {
                    $searchable[] = $c['name'];
                }
            }
            if (!empty($searchable)) {
                $parts = [];
                foreach ($searchable as $col) {
                    $qc = $driver->quoteIdentifier($col);
                    $parts[] = "CAST({$qc} AS CHAR) LIKE ?";
                    $bindings[] = "%{$search}%";
                }
                $conds[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        $whereSql = empty($conds) ? '' : ' WHERE ' . implode(' AND ', $conds);
        return [$whereSql, $bindings];
    }

    /**
     * Find a single row by primary key (composite supported).
     * $pkValues: ['col' => 'value', ...]
     */
    public function findRow(string $connection, string $table, array $pkValues): ?array
    {
        $driver = $this->cm->driver($connection);
        $qt = $driver->quoteIdentifier($table);
        $conds = [];
        $bindings = [];
        foreach ($pkValues as $col => $val) {
            $conds[] = $driver->quoteIdentifier($col) . ' = ?';
            $bindings[] = $val;
        }
        if (empty($conds)) return null;
        $sql = "SELECT * FROM {$qt} WHERE " . implode(' AND ', $conds) . ' LIMIT 1';
        $row = $driver->connection()->selectOne($sql, $bindings);
        return $row ? (array) $row : null;
    }

    /**
     * Run a global search across all (or selected) tables of a connection.
     * Returns array<table_name, ['matches'=>int, 'sample'=>row]>
     */
    public function globalSearch(string $connection, string $term, array $tables = [], int $perTableLimit = 5): array
    {
        if ($term === '') return [];
        $driver = $this->cm->driver($connection);
        $allTables = $tables ?: array_map(fn ($t) => $t['name'], $driver->tables());
        $results = [];
        foreach ($allTables as $t) {
            $columns = $driver->columns($t);
            $textCols = array_values(array_filter($columns, fn ($c) => preg_match('/char|text|enum|json|uuid/i', $c['type'])));
            if (empty($textCols)) continue;
            $qt = $driver->quoteIdentifier($t);
            $parts = [];
            $bindings = [];
            foreach ($textCols as $c) {
                $parts[] = $driver->quoteIdentifier($c['name']) . ' LIKE ?';
                $bindings[] = "%{$term}%";
            }
            $where = implode(' OR ', $parts);
            try {
                $count = (int) ($driver->connection()->selectOne("SELECT COUNT(*) as c FROM {$qt} WHERE {$where}", $bindings)->c ?? 0);
                if ($count > 0) {
                    $sample = $driver->connection()->select("SELECT * FROM {$qt} WHERE {$where} LIMIT {$perTableLimit}", $bindings);
                    $results[$t] = ['matches' => $count, 'sample' => array_map(fn ($r) => (array) $r, $sample)];
                }
            } catch (\Throwable $e) {
                // skip table errors silently
            }
        }
        return $results;
    }

    /**
     * Execute a raw SQL statement. Returns ['type','rows','columns','affected','duration_ms'].
     */
    public function runRaw(string $connection, string $sql): array
    {
        $driver = $this->cm->driver($connection);
        $conn = $driver->connection();
        $isRead = (bool) preg_match('/^\s*(SELECT|SHOW|EXPLAIN|DESC|DESCRIBE|PRAGMA|WITH)\b/i', $sql);
        $writesAllowed = (bool) config('dblens.sql_editor.allow_writes', false) && ! config('dblens.read_only', false);
        if (! $isRead && ! $writesAllowed) {
            throw new \RuntimeException('DbLens: write queries are disabled. Enable dblens.sql_editor.allow_writes to allow them.');
        }

        $start = microtime(true);
        if ($isRead) {
            $maxRows = (int) config('dblens.sql_editor.max_rows', 1000);
            $rows = $conn->select($sql);
            $duration = (int) ((microtime(true) - $start) * 1000);
            $arr = array_map(fn ($r) => (array) $r, $rows);
            $cols = $arr ? array_keys($arr[0]) : [];
            return [
                'type' => 'read',
                'rows' => array_slice($arr, 0, $maxRows),
                'columns' => $cols,
                'affected' => null,
                'duration_ms' => $duration,
                'truncated' => count($arr) > $maxRows,
            ];
        }
        $affected = $conn->affectingStatement($sql);
        $duration = (int) ((microtime(true) - $start) * 1000);
        return [
            'type' => 'write',
            'rows' => [],
            'columns' => [],
            'affected' => $affected,
            'duration_ms' => $duration,
            'truncated' => false,
        ];
    }
}
