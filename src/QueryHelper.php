<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;

final readonly class QueryHelper
{
    public function __construct(
        private TypeConverterInterface $typeConverter,
    ) {
    }

    /**
     * @param array<string, mixed> $where
     */
    public function applyWhere(QueryBuilder $qb, array $where, string $paramPrefix = ''): void
    {
        if ($where === []) {
            return;
        }

        $first = true;
        foreach ($where as $column => $value) {
            $paramName = $paramPrefix . $column;
            $predicate = $column . ' = :' . $paramName;

            if ($first) {
                $qb->where($predicate);
                $first = false;
            } else {
                $qb->andWhere($predicate);
            }

            $qb->setParameter($paramName, $this->typeConverter->toDb($value));
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
