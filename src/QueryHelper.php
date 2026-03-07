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
    public function applyWhere(QueryBuilder $qb, array $where): void
    {
        if ($where === []) {
            return;
        }

        $qb->where($this->convertWhereValues($where));
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

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>
     */
    private function convertWhereValues(array $where): array
    {
        $converted = [];
        foreach ($where as $key => $value) {
            if ($key === '$or' || $key === '$and') {
                if (!\is_array($value)) {
                    throw new \LogicException(\sprintf('WHERE "%s" expects array of groups.', $key));
                }

                $groups = [];
                foreach ($value as $group) {
                    if (!\is_array($group)) {
                        throw new \LogicException(\sprintf('WHERE "%s" group must be an array.', $key));
                    }

                    /** @var array<string, mixed> $group */
                    $groups[] = $this->convertWhereValues($group);
                }
                $converted[$key] = $groups;
                continue;
            }

            if ($value === null) {
                $converted[$key] = null;
                continue;
            }

            if (\is_array($value)) {
                $converted[$key] = $this->convertArrayValues($value);
                continue;
            }

            $converted[$key] = $this->typeConverter->toDb($value);
        }

        return $converted;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    private function convertArrayValues(array $values): array
    {
        $converted = [];
        foreach ($values as $key => $value) {
            $converted[$key] = $value === null ? null : $this->typeConverter->toDb($value);
        }

        return $converted;
    }
}
