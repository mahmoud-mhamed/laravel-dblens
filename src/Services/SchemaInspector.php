<?php

namespace MahmoudMhamed\DbLens\Services;

class SchemaInspector
{
    public function __construct(protected ConnectionManager $cm) {}

    public function tables(string $connection): array
    {
        return $this->cm->driver($connection)->tables();
    }

    public function tableInfo(string $connection, string $table): array
    {
        return $this->cm->driver($connection)->tableInfo($table);
    }

    public function columns(string $connection, string $table): array
    {
        return $this->cm->driver($connection)->columns($table);
    }

    public function indexes(string $connection, string $table): array
    {
        return $this->cm->driver($connection)->indexes($table);
    }

    public function foreignKeys(string $connection, string $table): array
    {
        return $this->cm->driver($connection)->foreignKeys($table);
    }

    public function incomingForeignKeys(string $connection, string $table): array
    {
        return $this->cm->driver($connection)->incomingForeignKeys($table);
    }

    public function primaryKey(string $connection, string $table): array
    {
        return $this->cm->driver($connection)->primaryKey($table);
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
        $out = [];
        foreach ($this->foreignKeys($connection, $table) as $fk) {
            $out[$fk['column']] = $fk;
        }
        return $out;
    }
}
