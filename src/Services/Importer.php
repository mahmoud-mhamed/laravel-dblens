<?php

namespace MahmoudMhamed\DbLens\Services;

use RuntimeException;
use SplFileObject;

class Importer
{
    public function __construct(protected ConnectionManager $cm, protected SchemaInspector $schema) {}

    protected function assertWritable(): void
    {
        if (config('dblens.read_only', false)) {
            throw new RuntimeException('DbLens is in read-only mode.');
        }
    }

    /**
     * Execute an uploaded SQL dump file. Statements are split on `;` at the end
     * of a line; strings/comments are respected to avoid splitting inside them.
     * Wrapped in a transaction.
     *
     * @return array{statements:int,executed:int}
     */
    public function importSql(string $connection, string $path): array
    {
        $this->assertWritable();
        $conn = $this->cm->connection($connection);

        $statements = $this->splitStatements(file_get_contents($path) ?: '');
        $count = count($statements);
        $executed = 0;

        $conn->beginTransaction();
        try {
            foreach ($statements as $sql) {
                if (trim($sql) === '') continue;
                $conn->statement($sql);
                $executed++;
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return ['statements' => $count, 'executed' => $executed];
    }

    /**
     * Import a CSV file into an existing table.
     * If $columnMap is empty, the CSV header row is used as the column list.
     *
     * @param  array<string,string>  $columnMap  csv_header => table_column
     * @return array{inserted:int}
     */
    public function importCsv(string $connection, string $table, string $path, array $columnMap = [], bool $hasHeader = true, string $delimiter = ','): array
    {
        $this->assertWritable();
        $driver = $this->cm->driver($connection);
        if (! $this->schema->tableExists($connection, $table)) {
            throw new RuntimeException("Table [{$table}] not found.");
        }
        $tableColumns = array_map(fn ($c) => $c['name'], $this->schema->columns($connection, $table));

        $file = new SplFileObject($path);
        $file->setCsvControl($delimiter);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);

        $header = null;
        if ($hasHeader) {
            $header = $file->current();
            $file->next();
        }

        // Determine target columns
        $targetColumns = [];
        if (! empty($columnMap)) {
            foreach ($columnMap as $csvName => $tableCol) {
                if (in_array($tableCol, $tableColumns, true)) {
                    $targetColumns[$csvName] = $tableCol;
                }
            }
        } elseif ($header) {
            foreach ($header as $h) {
                if (in_array($h, $tableColumns, true)) {
                    $targetColumns[$h] = $h;
                }
            }
        } else {
            // Without header and without map: take first N columns of the table
            $targetColumns = array_combine(range(0, count($tableColumns) - 1), $tableColumns);
        }

        if (empty($targetColumns)) {
            throw new RuntimeException('No matching columns between CSV and table.');
        }

        $qCols = implode(', ', array_map(fn ($c) => $driver->quoteIdentifier($c), array_values($targetColumns)));
        $placeholders = '(' . implode(',', array_fill(0, count($targetColumns), '?')) . ')';
        $sqlPrefix = 'INSERT INTO ' . $driver->quoteIdentifier($table) . ' (' . $qCols . ') VALUES ';

        $inserted = 0;
        $batch = [];
        $bindings = [];
        $batchSize = 200;
        $conn = $driver->connection();

        // Build header-index lookup for fast column extraction
        $indexMap = []; // tableColumn => csv-row index
        if ($header) {
            $headerMap = array_flip(array_map('strval', $header));
            foreach ($targetColumns as $csvKey => $tableCol) {
                $indexMap[$tableCol] = $headerMap[$csvKey] ?? null;
            }
        } else {
            foreach ($targetColumns as $idx => $tableCol) {
                $indexMap[$tableCol] = (int) $idx;
            }
        }

        $conn->beginTransaction();
        try {
            while (! $file->eof()) {
                $row = $file->current();
                $file->next();
                if (! is_array($row) || $row === [null]) continue;
                $values = [];
                foreach ($indexMap as $i) {
                    $values[] = $i === null ? null : ($row[$i] ?? null);
                }
                $batch[] = $placeholders;
                array_push($bindings, ...$values);

                if (count($batch) >= $batchSize) {
                    $conn->insert($sqlPrefix . implode(',', $batch), $bindings);
                    $inserted += count($batch);
                    $batch = []; $bindings = [];
                }
            }
            if (! empty($batch)) {
                $conn->insert($sqlPrefix . implode(',', $batch), $bindings);
                $inserted += count($batch);
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return ['inserted' => $inserted];
    }

    /**
     * Split a SQL dump into statements, respecting strings and -- /* */
    /* comments.
     *
     * @return array<int,string>
     */
    protected function splitStatements(string $sql): array
    {
        $statements = [];
        $buf = '';
        $len = strlen($sql);
        $inSingle = false; $inDouble = false; $inBacktick = false;
        $inLineComment = false; $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($ch === "\n") $inLineComment = false;
                continue;
            }
            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') { $inBlockComment = false; $i++; }
                continue;
            }
            if (! $inSingle && ! $inDouble && ! $inBacktick) {
                if ($ch === '-' && $next === '-') { $inLineComment = true; $i++; continue; }
                if ($ch === '#') { $inLineComment = true; continue; }
                if ($ch === '/' && $next === '*') { $inBlockComment = true; $i++; continue; }
            }

            if (! $inDouble && ! $inBacktick && $ch === "'" && ($i === 0 || $sql[$i-1] !== '\\')) $inSingle = ! $inSingle;
            elseif (! $inSingle && ! $inBacktick && $ch === '"' && ($i === 0 || $sql[$i-1] !== '\\')) $inDouble = ! $inDouble;
            elseif (! $inSingle && ! $inDouble && $ch === '`') $inBacktick = ! $inBacktick;

            if ($ch === ';' && ! $inSingle && ! $inDouble && ! $inBacktick) {
                $stmt = trim($buf);
                if ($stmt !== '') $statements[] = $stmt;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $tail = trim($buf);
        if ($tail !== '') $statements[] = $tail;
        return $statements;
    }
}
