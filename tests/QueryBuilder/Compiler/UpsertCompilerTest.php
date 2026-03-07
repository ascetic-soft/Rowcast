<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpsertCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\MysqlDialect;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\PostgresDialect;
use PHPUnit\Framework\TestCase;

final class UpsertCompilerTest extends TestCase
{
    public function testCompileForPostgres(): void
    {
        $sql = new UpsertCompiler(
            table: 'cards',
            values: ['id' => ':id', 'title' => ':title'],
            conflictColumns: ['id'],
            updateColumns: ['title'],
            dialect: new PostgresDialect(),
        )->compile();

        self::assertSame(
            'INSERT INTO cards (id, title) VALUES (:id, :title) ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title',
            $sql,
        );
    }

    public function testCompileForMysql(): void
    {
        $sql = new UpsertCompiler(
            table: 'cards',
            values: ['id' => ':id', 'title' => ':title'],
            conflictColumns: ['id'],
            updateColumns: ['title'],
            dialect: new MysqlDialect(),
        )->compile();

        self::assertSame(
            'INSERT INTO cards (id, title) VALUES (:id, :title) ON DUPLICATE KEY UPDATE title = VALUES(title)',
            $sql,
        );
    }
}
