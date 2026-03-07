<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Dialect;

final class DialectFactory
{
    public static function fromDriverName(string $driverName): DialectInterface
    {
        return match ($driverName) {
            'mysql' => new MysqlDialect(),
            'pgsql' => new PostgresDialect(),
            'sqlite' => new SqliteDialect(),
            default => new GenericDialect($driverName),
        };
    }
}
