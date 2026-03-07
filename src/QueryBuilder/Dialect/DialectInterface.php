<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

interface DialectInterface
{
    public function applyLimitOffset(string $sql, ?int $limit, ?int $offset): string;

    /**
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    public function compileUpsertClause(array $conflictColumns, array $updateColumns): string;
}
