<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

abstract class AbstractOnConflictDialect extends AbstractStandardDialect
{
    public function compileUpsertClause(array $conflictColumns, array $updateColumns): string
    {
        if ($conflictColumns === []) {
            throw new \LogicException('UPSERT requires conflict columns for pgsql/sqlite.');
        }

        if ($updateColumns === []) {
            return ' ON CONFLICT (' . implode(', ', $conflictColumns) . ') DO NOTHING';
        }

        $parts = array_map(
            static fn (string $column): string => $column . ' = EXCLUDED.' . $column,
            $updateColumns,
        );

        return ' ON CONFLICT (' . implode(', ', $conflictColumns) . ')'
            . ' DO UPDATE SET ' . implode(', ', $parts);
    }
}
