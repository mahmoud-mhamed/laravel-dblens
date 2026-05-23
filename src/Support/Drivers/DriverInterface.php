<?php

namespace MahmoudMhamed\DbLens\Support\Drivers;

use Illuminate\Database\Connection;

interface DriverInterface
{
    public function connection(): Connection;

    public function name(): string;

    public function quoteIdentifier(string $ident): string;

    /**
     * Wrap a (typically already-quoted) expression in a CAST to a textual
     * type, so it can be compared with LIKE regardless of the column's real
     * type. Implementations vary per database — MySQL uses CHAR, Postgres
     * uses TEXT, SQLite uses TEXT.
     */
    public function castToText(string $expression): string;

    /** @return array<int,array{name:string,rows:int,size:int,engine:?string,collation:?string,comment:?string}> */
    public function tables(): array;

    /** @return array{rows:int,size:int,engine:?string,collation:?string,comment:?string,auto_increment:?int,created_at:?string,updated_at:?string} */
    public function tableInfo(string $table): array;

    /** @return array<int,array{name:string,type:string,nullable:bool,default:mixed,key:?string,extra:?string,comment:?string}> */
    public function columns(string $table): array;

    /** @return array<int,array{name:string,columns:array<int,string>,unique:bool,primary:bool}> */
    public function indexes(string $table): array;

    /**
     * @return array<int,array{name:string,column:string,foreign_table:string,foreign_column:string,on_update:?string,on_delete:?string}>
     */
    public function foreignKeys(string $table): array;

    /**
     * Incoming foreign keys (other tables that reference this one).
     * @return array<int,array{name:string,table:string,column:string,foreign_column:string}>
     */
    public function incomingForeignKeys(string $table): array;

    /** @return array<int,string> column names that form the primary key */
    public function primaryKey(string $table): array;

    /**
     * Fast, possibly approximate row count from the engine's statistics.
     * Returns null if no estimate is available — caller should fall back to COUNT(*).
     */
    public function approximateRowCount(string $table): ?int;

    public function dropForeignKey(string $table, string $fkName): void;

    /** @param array{type:string,nullable:bool,default:?string,after:?string,comment:?string} $def */
    public function addColumn(string $table, string $column, array $def): void;

    /** @param array{type:string,nullable:bool,default:?string,comment:?string} $def */
    public function modifyColumn(string $table, string $column, array $def): void;

    public function renameColumn(string $table, string $from, string $to): void;

    public function dropColumn(string $table, string $column): void;

    /** @param array<int,string> $columns */
    public function addIndex(string $table, string $name, array $columns, bool $unique = false): void;

    public function dropIndex(string $table, string $name): void;

    public function addForeignKey(string $table, string $name, string $column, string $refTable, string $refColumn, ?string $onUpdate = null, ?string $onDelete = null): void;

    public function truncateTable(string $table): void;

    public function dropTable(string $table): void;

    public function renameTable(string $from, string $to): void;

    /**
     * @param array<int,array{name:string,type:string,nullable:bool,default:?string,primary:bool,auto_increment:bool}> $columns
     */
    public function createTable(string $name, array $columns): void;

    /**
     * Return the DDL CREATE TABLE statement, or null if not supported.
     */
    public function createTableSql(string $table): ?string;

    /**
     * Bulk: every column of every table in one query.
     * @return array<string,array<int,array{name:string,type:string,nullable:bool,key:?string}>>
     *         table_name => [columns…]
     */
    public function allColumns(): array;

    /**
     * Bulk: every outgoing FK in the schema in one query.
     * @return array<int,array{table:string,name:string,column:string,foreign_table:string,foreign_column:string}>
     */
    public function allForeignKeys(): array;

    /** @return array<int,array{name:string,definition:?string}> */
    public function views(): array;

    /** @return array<int,array{name:string,type:string,definition:?string}>  type = PROCEDURE|FUNCTION */
    public function routines(): array;

    /** @return array<int,array{name:string,table:string,event:string,timing:string,definition:?string}> */
    public function triggers(): array;

    /** @return array<int,array{name:string,definition:?string,schedule:?string,status:?string}> */
    public function events(): array;

    public function dropView(string $name): void;

    /** @param string $type PROCEDURE|FUNCTION */
    public function dropRoutine(string $name, string $type): void;

    public function dropTrigger(string $name): void;

    public function dropEvent(string $name): void;
}
