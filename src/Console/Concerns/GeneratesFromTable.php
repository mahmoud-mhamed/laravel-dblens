<?php

namespace MahmoudMhamed\DbLens\Console\Concerns;

use Illuminate\Support\Str;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

trait GeneratesFromTable
{
    protected function resolveConnection(ConnectionManager $cm): string
    {
        return (string) ($this->option('connection') ?: ($cm->available()[0] ?? config('database.default')));
    }

    protected function ensureTable(SchemaInspector $schema, string $connection, string $table): bool
    {
        if (! $schema->tableExists($connection, $table)) {
            $this->error("Table [{$table}] not found on connection [{$connection}].");

            return false;
        }

        return true;
    }

    /** Singular StudlyCase model name for a table (orders → Order). */
    protected function modelName(string $table): string
    {
        return Str::studly(Str::singular($table));
    }

    /** Columns a user would mass-assign: everything but the auto key, timestamps, soft-delete. */
    protected function fillableColumns(array $columns, array $pk): array
    {
        $managed = ['created_at', 'updated_at', 'deleted_at'];
        $out = [];
        foreach ($columns as $c) {
            $name = $c['name'];
            $isAutoInc = stripos((string) ($c['extra'] ?? ''), 'auto_increment') !== false;
            $isIntPk = count($pk) === 1 && in_array($name, $pk, true) && preg_match('/int/i', (string) $c['type']);
            if ($isAutoInc || $isIntPk || in_array($name, $managed, true)) continue;
            $out[] = $name;
        }

        return $out;
    }

    /** Write a file, honouring --force. Returns the path, or null if it exists and not forced. */
    protected function putFile(string $path, string $contents): ?string
    {
        if (file_exists($path) && ! $this->option('force')) {
            $this->warn("Exists (use --force to overwrite): {$path}");

            return null;
        }
        $dir = dirname($path);
        if (! is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($path, $contents);
        $this->info("✓ {$path}");

        return $path;
    }
}
