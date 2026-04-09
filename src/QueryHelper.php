<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

final readonly class QueryHelper
{
    /**
     * @param array<string, mixed> $where
     */
    public function applyWhere(QueryBuilder $qb, array $where): void
    {
        if ($where !== []) {
            $qb->where($where);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function createPlaceholders(array $data): array
    {
        $values = [];
        foreach (array_keys($data) as $column) {
            $values[$column] = ':' . $column;
        }

        return $values;
    }
}
