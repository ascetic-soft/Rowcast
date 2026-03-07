<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

final class MysqlDialect extends AbstractStandardDialect
{
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
