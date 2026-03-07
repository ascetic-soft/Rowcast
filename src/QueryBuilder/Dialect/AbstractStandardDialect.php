<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

abstract class AbstractStandardDialect implements DialectInterface
{
    final public function applyLimitOffset(string $sql, ?int $limit, ?int $offset): string
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

    /**
     * @return array<string, true>
     */
    public function getSupportedOperators(): array
    {
        return [
            '>' => true,
            '>=' => true,
            '<' => true,
            '<=' => true,
            'LIKE' => true,
            'NOT LIKE' => true,
        ];
    }
}
