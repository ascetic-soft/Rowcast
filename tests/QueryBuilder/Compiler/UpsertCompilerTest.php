<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpsertCompiler;
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
            driverName: 'pgsql',
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
            driverName: 'mysql',
        )->compile();

        self::assertSame(
            'INSERT INTO cards (id, title) VALUES (:id, :title) ON DUPLICATE KEY UPDATE title = VALUES(title)',
            $sql,
        );
    }
}
