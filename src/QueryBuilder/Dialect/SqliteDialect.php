<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

final class SqliteDialect extends AbstractOnConflictDialect
{
    public function getMaxBindParameters(): int
    {
        return 999;
    }
}
