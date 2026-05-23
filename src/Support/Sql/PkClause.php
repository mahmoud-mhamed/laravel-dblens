<?php

namespace MahmoudMhamed\DbLens\Support\Sql;

use MahmoudMhamed\DbLens\Support\Drivers\DriverInterface;

/**
 * Build `WHERE pk_a = ? AND pk_b = ?` clauses (and a bag of bindings) for a
 * single-row lookup or mutation. Centralising this avoids subtly different
 * loops in RowEditor / QueryRunner / RowController.
 */
class PkClause
{
    /**
     * @param  array<string,mixed>  $pkValues  column => value
     * @return array{0:string,1:array<int,mixed>}  [sqlFragment, bindings]
     *         The fragment is `col1 = ? AND col2 = ?` — caller adds the `WHERE`.
     */
    public static function build(DriverInterface $driver, array $pkValues): array
    {
        $conds = [];
        $bindings = [];
        foreach ($pkValues as $col => $val) {
            $conds[] = $driver->quoteIdentifier((string) $col) . ' = ?';
            $bindings[] = $val;
        }
        return [implode(' AND ', $conds), $bindings];
    }
}
