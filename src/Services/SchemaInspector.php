<?php

namespace MahmoudMhamed\DbLens\Services;

class SchemaInspector
{
    /** @var array<string,array{value:mixed,expires_at:int}> */
    protected array $cache = [];

    public function __construct(protected ConnectionManager $cm) {}

    protected function remember(string $key, \Closure $cb)
    {
        $ttl = (int) config('dblens.schema_cache.ttl_seconds', 60);
        $now = time();
        if (isset($this->cache[$key])) {
            $entry = $this->cache[$key];
            if ($ttl <= 0 || $entry['expires_at'] > $now) {
                return $entry['value'];
            }
        }
        $value = $cb();
        $this->cache[$key] = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? $now + $ttl : PHP_INT_MAX,
        ];
        return $value;
    }

    /**
     * Drop all cached schema lookups. Call after DDL so the next read sees
     * the new structure.
     */
    public function flush(?string $connection = null): void
    {
        if ($connection === null) {
            $this->cache = [];
            return;
        }
        foreach (array_keys($this->cache) as $key) {
            if (str_contains($key, ":{$connection}:") || str_ends_with($key, ":{$connection}")) {
                unset($this->cache[$key]);
            }
        }
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
