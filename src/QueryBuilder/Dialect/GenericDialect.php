<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

final readonly class GenericDialect implements DialectInterface
{
    public function __construct(
        private string $driverName,
    ) {
    }

    public function applyLimitOffset(string $sql, ?int $limit, ?int $offset): string
    {
        return $sql;
    }

    public function compileUpsertClause(array $conflictColumns, array $updateColumns): string
    {
        throw new \LogicException(\sprintf('UPSERT is not supported for driver "%s".', $this->driverName));
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
