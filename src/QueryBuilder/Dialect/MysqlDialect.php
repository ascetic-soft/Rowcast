<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

final class MysqlDialect implements DialectInterface
{
    public function applyLimitOffset(string $sql, ?int $limit, ?int $offset): string
    {
        if ($limit === null) {
            return $sql;
        }

        $sql .= ' LIMIT ' . $limit;

        if (($offset ?? 0) > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }

    public function compileUpsertClause(array $conflictColumns, array $updateColumns): string
    {
        if ($updateColumns === []) {
            return '';
        }

        $parts = array_map(
            static fn (string $column): string => $column . ' = VALUES(' . $column . ')',
            $updateColumns,
        );

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $parts);
    }
}
