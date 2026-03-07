<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

final class SqlFragments
{
    /**
     * @param array<string, string> $values column => placeholder
     */
    public static function buildInsertSql(string $table, array $values): string
    {
        $columns = array_keys($values);
        $placeholders = array_values($values);

        return 'INSERT INTO ' . $table
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
    }

    /**
     * @param list<string> $where
     */
    public static function compileWhere(array $where): ?string
    {
        if ($where === []) {
            return null;
        }

        return 'WHERE ' . implode(' AND ', $where);
    }
}
