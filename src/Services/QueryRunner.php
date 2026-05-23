<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;
use MahmoudMhamed\DbLens\Support\Sql\PkClause;

class QueryRunner
{
    public function __construct(protected ConnectionManager $cm, protected ?SchemaInspector $schema = null) {}

    protected function schema(): SchemaInspector
    {
        return $this->schema ??= app(SchemaInspector::class);
    }

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

        $columns = $this->schema()->columns($connection, $table);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        [$whereSql, $bindings] = $this->buildWhere($driver, $columns, $filters, $search);

        $orderSql = '';
        if ($orderBy && in_array($orderBy, $columnNames, true)) {
            $orderSql = ' ORDER BY ' . $driver->quoteIdentifier($orderBy) . ' ' . $orderDir;
        } else {
            $pk = $this->schema()->primaryKey($connection, $table);
            if (!empty($pk)) {
                $orderSql = ' ORDER BY ' . $driver->quoteIdentifier($pk[0]) . ' ASC';
            }
        }

        $total = null;
        $approximate = false;
        $estThreshold = (int) config('dblens.browse.approx_count_threshold', 100000);
        if ($whereSql === '' && $estThreshold > 0) {
            $est = $driver->approximateRowCount($table);
            if ($est !== null && $est >= $estThreshold) {
                $total = $est;
                $approximate = true;
            }
        }
        if ($total === null) {
            $totalSql = "SELECT COUNT(*) as c FROM {$qt}{$whereSql}";
            $total = (int) ($conn->selectOne($totalSql, $bindings)->c ?? 0);
        }

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$qt}{$whereSql}{$orderSql} LIMIT {$perPage} OFFSET {$offset}";
        $rows = $conn->select($sql, $bindings);
        $rows = array_map(fn ($r) => (array) $r, $rows);

        $paginator = new LengthAwarePaginator(
            $rows, $total, $perPage, $page,
            ['path' => Request::url(), 'query' => Request::query()]
        );

        return ['rows' => $rows, 'paginator' => $paginator, 'sql' => $sql, 'total' => $total, 'approximate' => $approximate];
    }

    /**
     * Build the WHERE clause + bindings from filter / search inputs.
     * @return array{0:string,1:array<int,mixed>}  [whereSql, bindings]
     */
    public function buildWhere($driver, array $columns, array $filters, string $search): array
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
                    $parts[] = $driver->castToText($qc) . ' LIKE ?';
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
        if (empty($pkValues)) return null;
        $driver = $this->cm->driver($connection);
        $qt = $driver->quoteIdentifier($table);
        [$where, $bindings] = PkClause::build($driver, $pkValues);
        $sql = "SELECT * FROM {$qt} WHERE {$where} LIMIT 1";
        $row = $driver->connection()->selectOne($sql, $bindings);
        return $row ? (array) $row : null;
    }

    /**
     * Run a global search across all (or selected) tables of a connection.
     * Returns array<table_name, ['matches'=>int, 'sample'=>row]>
     */
    public function globalSearch(string $connection, string $term, array $tables = [], ?int $perTableLimit = null): array
    {
        if ($term === '') return [];
        $perTableLimit = $perTableLimit ?? (int) config('dblens.global_search.per_table_limit', 5);
        $maxTables = (int) config('dblens.global_search.max_tables', 100);
        $timeoutMs = (int) config('dblens.global_search.statement_timeout_ms', 2000);

        $driver = $this->cm->driver($connection);
        $allTables = $tables ?: array_map(fn ($t) => $t['name'], $driver->tables());
        if ($maxTables > 0 && count($allTables) > $maxTables) {
            $allTables = array_slice($allTables, 0, $maxTables);
        }
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
            $countSql = $this->applyStatementTimeout($driver, "SELECT COUNT(*) as c FROM {$qt} WHERE {$where}", $timeoutMs);
            $sampleSql = $this->applyStatementTimeout($driver, "SELECT * FROM {$qt} WHERE {$where} LIMIT {$perTableLimit}", $timeoutMs);
            try {
                $count = (int) ($driver->connection()->selectOne($countSql, $bindings)->c ?? 0);
                if ($count > 0) {
                    $sample = $driver->connection()->select($sampleSql, $bindings);
                    $results[$t] = ['matches' => $count, 'sample' => array_map(fn ($r) => (array) $r, $sample)];
                }
            } catch (\Throwable $e) {
                // Per-table errors (timeout, permission, weird collation) are
                // expected at this scale — just skip the table.
            }
        }
        return $results;
    }

    /**
     * Best-effort per-statement timeout. MySQL has a SELECT-only hint, so we
     * can only patch SELECT … queries. Other drivers fall back to the raw
     * SQL (server-side timeouts still apply if configured).
     */
    protected function applyStatementTimeout($driver, string $sql, int $timeoutMs): string
    {
        if ($timeoutMs <= 0) return $sql;
        if ($driver->name() === 'mysql' && preg_match('/^\s*SELECT\s+/i', $sql)) {
            return preg_replace('/^(\s*SELECT)\s+/i', '$1 /*+ MAX_EXECUTION_TIME(' . $timeoutMs . ') */ ', $sql, 1);
        }
        return $sql;
    }

    /**
     * Run EXPLAIN on the given SELECT. Driver-aware.
     */
    public function explain(string $connection, string $sql): array
    {
        $driver = $this->cm->driver($connection);
        $conn = $driver->connection();
        $trimmed = ltrim($sql);
        if (! preg_match('/^(SELECT|WITH|UPDATE|DELETE|INSERT)\b/i', $trimmed)) {
            throw new \RuntimeException('EXPLAIN supports SELECT/WITH/UPDATE/DELETE/INSERT statements.');
        }
        $stripped = rtrim($trimmed, "; \t\n\r\0\x0B");
        $explainSql = match ($driver->name()) {
            'sqlite' => 'EXPLAIN QUERY PLAN '.$stripped,
            'pgsql'  => 'EXPLAIN (ANALYZE FALSE, VERBOSE FALSE, COSTS TRUE, FORMAT TEXT) '.$stripped,
            default  => 'EXPLAIN '.$stripped,
        };
        $start = microtime(true);
        $rows = $conn->select($explainSql);
        $duration = (int) ((microtime(true) - $start) * 1000);
        $arr = array_map(fn ($r) => (array) $r, $rows);
        $cols = $arr ? array_keys($arr[0]) : [];
        return [
            'type' => 'read',
            'rows' => $arr,
            'columns' => $cols,
            'affected' => null,
            'duration_ms' => $duration,
            'truncated' => false,
            'explain_sql' => $explainSql,
        ];
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

        $logger = app(ActivityLogger::class);
        $props = ['connection' => $connection, 'affected' => $affected, 'duration_ms' => $duration];
        if (config('dblens.activity_log.capture_sql', false)) {
            $props['sql'] = $sql;
        }
        $logger->log('sql_executed', "Executed write SQL on {$connection} (affected {$affected})", $props);

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
