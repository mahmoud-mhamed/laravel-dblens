<?php

namespace MahmoudMhamed\DbLens\Services;

use RuntimeException;

class TableEditor
{
    public function __construct(protected ConnectionManager $cm) {}

    protected function assertWritable(): void
    {
        if (config('dblens.read_only', false)) {
            throw new RuntimeException('DbLens is in read-only mode.');
        }
    }

    public function addColumn(string $connection, string $table, string $column, array $def): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->addColumn($table, $column, $def);
    }

    public function modifyColumn(string $connection, string $table, string $column, array $def): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->modifyColumn($table, $column, $def);
    }

    public function renameColumn(string $connection, string $table, string $from, string $to): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->renameColumn($table, $from, $to);
    }

    public function dropColumn(string $connection, string $table, string $column): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->dropColumn($table, $column);
    }

    public function addIndex(string $connection, string $table, string $name, array $columns, bool $unique = false): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->addIndex($table, $name, $columns, $unique);
    }

    public function dropIndex(string $connection, string $table, string $name): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->dropIndex($table, $name);
    }

    public function addForeignKey(string $connection, string $table, string $name, string $column, string $refTable, string $refColumn, ?string $onUpdate, ?string $onDelete): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->addForeignKey($table, $name, $column, $refTable, $refColumn, $onUpdate, $onDelete);
    }

    public function truncate(string $connection, string $table): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->truncateTable($table);
    }

    public function drop(string $connection, string $table): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->dropTable($table);
    }

    public function rename(string $connection, string $from, string $to): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->renameTable($from, $to);
    }

    public function create(string $connection, string $name, array $columns): void
    {
        $this->assertWritable();
        $this->cm->driver($connection)->createTable($name, $columns);
    }
}
