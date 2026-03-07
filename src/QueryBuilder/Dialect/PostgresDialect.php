<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

final class PostgresDialect extends AbstractOnConflictDialect
{
    /**
     * @return array<string, true>
     */
    public function getSupportedOperators(): array
    {
        return parent::getSupportedOperators() + [
            'ILIKE' => true,
            'NOT ILIKE' => true,
        ];
    }
}
