<?php

namespace MahmoudMhamed\DbLens\Services;

use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Exporter
{
    public function __construct(
        protected ConnectionManager $cm,
        protected SchemaInspector $schema,
        protected QueryRunner $runner,
    ) {}

    public function tableResponse(string $connection, string $table, string $format, array $options = []): StreamedResponse
    {
        $format = strtolower($format);
        $suffix = ! empty($options['filters']) || ! empty($options['search']) ? '-filtered' : '';
        $filename = sprintf('%s-%s%s-%s.%s', $connection, $table, $suffix, date('Ymd-His'), $format);
        $mime = match ($format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => 'application/sql',
        };

        // Resolve filter SQL once so we can reuse during streaming
        [$whereSql, $bindings] = $this->resolveWhere($connection, $table, $options);

        return new StreamedResponse(function () use ($connection, $table, $format, $whereSql, $bindings) {
            $stream = fopen('php://output', 'w');
            match ($format) {
                'csv' => $this->writeCsv($stream, $connection, $table, $whereSql, $bindings),
                'json' => $this->writeJson($stream, $connection, $table, $whereSql, $bindings),
                default => $this->writeSqlTable($stream, $connection, $table, true, true, $whereSql, $bindings),
            };
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function resolveWhere(string $connection, string $table, array $options): array
    {
        $filters = (array) ($options['filters'] ?? []);
        $search = (string) ($options['search'] ?? '');
        if (empty($filters) && $search === '') {
            return ['', []];
        }
        $driver = $this->cm->driver($connection);
        $columns = $this->schema->columns($connection, $table);
        return $this->runner->buildWhere($driver, $columns, $filters, $search);
    }

    /**
     * Dispatch to the right format-specific custom exporter.
     */
    public function custom(string $connection, array $structureTables, array $dataTables, string $format = 'sql')
    {
        $format = strtolower($format);
        return match ($format) {
            'csv' => $this->customCsvZipResponse($connection, $dataTables),
            'json' => $this->customJsonResponse($connection, $dataTables),
            default => $this->customResponse($connection, $structureTables, $dataTables),
        };
    }

    /**
     * Stream a customized SQL dump.
     *
     * @param  array<int,string>  $structureTables  tables whose CREATE TABLE should be included
     * @param  array<int,string>  $dataTables       tables whose INSERTs should be included
     */
    public function customResponse(string $connection, array $structureTables, array $dataTables): StreamedResponse
    {
        $filename = sprintf('%s-%s-%s.sql', $connection, $this->cm->databaseName($connection), date('Ymd-His'));
        $structureSet = array_flip($structureTables);
        $dataSet = array_flip($dataTables);
        $order = array_values(array_unique(array_merge($structureTables, $dataTables)));

        return new StreamedResponse(function () use ($connection, $order, $structureSet, $dataSet) {
            $stream = fopen('php://output', 'w');
            $tablesAll = array_map(fn ($t) => $t['name'], $this->schema->tables($connection));
            $valid = array_intersect($order, $tablesAll);

            fwrite($stream, "-- DbLens export\n-- Database: {$this->cm->databaseName($connection)}\n-- Generated: " . date('c') . "\n");
            fwrite($stream, "-- Structure: " . count($structureSet) . " table(s) · Data: " . count($dataSet) . " table(s)\n\n");

            foreach ($valid as $t) {
                $withCreate = isset($structureSet[$t]);
                $withData = isset($dataSet[$t]);
                fwrite($stream, "\n-- ───────── {$t} ─────────\n");
                $this->writeSqlTable($stream, $connection, $t, $withCreate, $withData);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Build a ZIP of CSV files (one per selected table) and stream it.
     * @param  array<int,string>  $dataTables
     */
    public function customCsvZipResponse(string $connection, array $dataTables): StreamedResponse
    {
        $filename = sprintf('%s-%s-%s.zip', $connection, $this->cm->databaseName($connection), date('Ymd-His'));
        $tablesAll = array_map(fn ($t) => $t['name'], $this->schema->tables($connection));
        $valid = array_values(array_intersect($dataTables, $tablesAll));

        return new StreamedResponse(function () use ($connection, $valid) {
            $tmp = tempnam(sys_get_temp_dir(), 'dblens-csv-');
            $zip = new \ZipArchive();
            $zip->open($tmp, \ZipArchive::OVERWRITE);

            foreach ($valid as $table) {
                $csvTmp = tempnam(sys_get_temp_dir(), 'dblens-csv-row-');
                $fh = fopen($csvTmp, 'w');
                $this->writeCsv($fh, $connection, $table);
                fclose($fh);
                $zip->addFile($csvTmp, $table . '.csv');
                // ZipArchive keeps file handles open until close — files survive past tempnam GC
            }
            $zip->close();

            readfile($tmp);
            @unlink($tmp);
            // Clean csv tmp files: ZipArchive::close consumed them already
            foreach (glob(sys_get_temp_dir() . '/dblens-csv-row-*') ?: [] as $f) @unlink($f);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream a single JSON file like { "tableA": [...], "tableB": [...] }.
     * @param  array<int,string>  $dataTables
     */
    public function customJsonResponse(string $connection, array $dataTables): StreamedResponse
    {
        $filename = sprintf('%s-%s-%s.json', $connection, $this->cm->databaseName($connection), date('Ymd-His'));
        $tablesAll = array_map(fn ($t) => $t['name'], $this->schema->tables($connection));
        $valid = array_values(array_intersect($dataTables, $tablesAll));

        return new StreamedResponse(function () use ($connection, $valid) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "{\n");
            $firstTable = true;
            foreach ($valid as $table) {
                $prefix = $firstTable ? '' : ",\n";
                fwrite($stream, $prefix . '  ' . json_encode($table) . ": [");
                $firstRow = true;
                foreach ($this->rowGenerator($connection, $table) as $row) {
                    $sep = $firstRow ? "\n" : ",\n";
                    fwrite($stream, $sep . '    ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $firstRow = false;
                }
                fwrite($stream, ($firstRow ? '' : "\n  ") . ']');
                $firstTable = false;
            }
            fwrite($stream, "\n}\n");
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function databaseResponse(string $connection): StreamedResponse
    {
        $filename = sprintf('%s-%s-%s.sql', $connection, $this->cm->databaseName($connection), date('Ymd-His'));
        $tables = array_map(fn ($t) => $t['name'], $this->schema->tables($connection));

        return new StreamedResponse(function () use ($connection, $tables) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "-- DbLens export\n-- Database: {$this->cm->databaseName($connection)}\n-- Generated: " . date('c') . "\n\n");
            foreach ($tables as $t) {
                fwrite($stream, "\n-- ───────── {$t} ─────────\n");
                $this->writeSqlTable($stream, $connection, $t, true);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function writeCsv($stream, string $connection, string $table, string $whereSql = '', array $bindings = []): void
    {
        $columns = $this->schema->columns($connection, $table);
        $colNames = array_map(fn ($c) => $c['name'], $columns);
        fputcsv($stream, $colNames);
        foreach ($this->rowGenerator($connection, $table, $whereSql, $bindings) as $row) {
            $out = [];
            foreach ($colNames as $c) {
                $out[] = $row[$c] ?? '';
            }
            fputcsv($stream, $out);
        }
    }

    protected function writeJson($stream, string $connection, string $table, string $whereSql = '', array $bindings = []): void
    {
        fwrite($stream, "[");
        $first = true;
        foreach ($this->rowGenerator($connection, $table, $whereSql, $bindings) as $row) {
            $prefix = $first ? "\n" : ",\n";
            fwrite($stream, $prefix . '  ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $first = false;
        }
        fwrite($stream, "\n]\n");
    }

    protected function writeSqlTable($stream, string $connection, string $table, bool $withCreate, bool $withData = true, string $whereSql = '', array $bindings = []): void
    {
        $driver = $this->cm->driver($connection);
        $qt = $driver->quoteIdentifier($table);

        if ($withCreate) {
            $ddl = $driver->createTableSql($table);
            if ($ddl) {
                fwrite($stream, "DROP TABLE IF EXISTS {$qt};\n");
                fwrite($stream, rtrim($ddl, ";\n") . ";\n\n");
            }
        }

        if (! $withData) {
            return;
        }

        $columns = $this->schema->columns($connection, $table);
        $colNames = array_map(fn ($c) => $c['name'], $columns);
        $quotedCols = implode(', ', array_map(fn ($c) => $driver->quoteIdentifier($c), $colNames));
        $pdo = $driver->connection()->getPdo();

        $batch = [];
        $batchSize = 100;
        foreach ($this->rowGenerator($connection, $table, $whereSql, $bindings) as $row) {
            $vals = [];
            foreach ($colNames as $c) {
                $v = $row[$c] ?? null;
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($v) || is_float($v)) {
                    $vals[] = (string) $v;
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            $batch[] = '(' . implode(', ', $vals) . ')';
            if (count($batch) >= $batchSize) {
                fwrite($stream, "INSERT INTO {$qt} ({$quotedCols}) VALUES\n  " . implode(",\n  ", $batch) . ";\n");
                $batch = [];
            }
        }
        if (! empty($batch)) {
            fwrite($stream, "INSERT INTO {$qt} ({$quotedCols}) VALUES\n  " . implode(",\n  ", $batch) . ";\n");
        }
        fwrite($stream, "\n");
    }

    /**
     * Yield rows one-at-a-time using PDO cursor to keep memory low.
     */
    protected function rowGenerator(string $connection, string $table, string $whereSql = '', array $bindings = []): Generator
    {
        $driver = $this->cm->driver($connection);
        $pdo = $driver->connection()->getPdo();
        $sql = 'SELECT * FROM ' . $driver->quoteIdentifier($table) . $whereSql;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }
}
