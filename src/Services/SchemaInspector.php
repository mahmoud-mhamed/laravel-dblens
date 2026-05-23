<?php

namespace MahmoudMhamed\DbLens\Services;

class SchemaInspector
{
    protected array $cache = [];

    public function __construct(protected ConnectionManager $cm) {}

    protected function remember(string $key, \Closure $cb)
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $cb();
        }
        return $this->cache[$key];
    }

    public function tables(string $connection): array
    {
        return $this->remember("tables:{$connection}", fn () => $this->cm->driver($connection)->tables());
    }

    public function tableInfo(string $connection, string $table): array
    {
        return $this->remember("info:{$connection}:{$table}", fn () => $this->cm->driver($connection)->tableInfo($table));
    }

    public function columns(string $connection, string $table): array
    {
        return $this->remember("cols:{$connection}:{$table}", fn () => $this->cm->driver($connection)->columns($table));
    }

    public function indexes(string $connection, string $table): array
    {
        return $this->remember("idx:{$connection}:{$table}", fn () => $this->cm->driver($connection)->indexes($table));
    }

    public function foreignKeys(string $connection, string $table): array
    {
        return $this->remember("fk:{$connection}:{$table}", fn () => $this->cm->driver($connection)->foreignKeys($table));
    }

    public function incomingForeignKeys(string $connection, string $table): array
    {
        return $this->remember("ifk:{$connection}:{$table}", fn () => $this->cm->driver($connection)->incomingForeignKeys($table));
    }

    public function primaryKey(string $connection, string $table): array
    {
        return $this->remember("pk:{$connection}:{$table}", fn () => $this->cm->driver($connection)->primaryKey($table));
    }

    public function tableExists(string $connection, string $table): bool
    {
        foreach ($this->tables($connection) as $t) {
            if ($t['name'] === $table) return true;
        }
        return false;
    }

    /**
     * Map column-name => foreign-key array for a table (for quick FK lookups in views).
     */
    public function foreignKeyByColumn(string $connection, string $table): array
    {
        return $this->remember("fkByCol:{$connection}:{$table}", function () use ($connection, $table) {
            $out = [];
            foreach ($this->foreignKeys($connection, $table) as $fk) {
                $out[$fk['column']] = $fk;
            }
            return $out;
        });
    }
}
