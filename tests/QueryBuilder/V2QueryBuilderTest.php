<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder;

use AsceticSoft\Rowcast\Connection;
use PHPUnit\Framework\TestCase;

final class V2QueryBuilderTest extends TestCase
{
    public function testBuildsUpsertSqlForSqlite(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->upsert('cards')
            ->values(['id' => ':id', 'title' => ':title'])
            ->onConflict('id')
            ->doUpdateSet(['title'])
            ->getSQL();

        self::assertSame(
            'INSERT INTO cards (id, title) VALUES (:id, :title) ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title',
            $sql,
        );
    }
}
