<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\QueryHelper;
use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;
use PHPUnit\Framework\TestCase;

final class QueryHelperTest extends TestCase
{
    public function testCreatePlaceholdersAndApplyWhereWithConversion(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $helper = new QueryHelper(TypeConverterRegistry::defaults());
        $qb = $connection->createQueryBuilder()->select('*')->from('users');

        self::assertSame(['id' => ':id', 'name' => ':name'], $helper->createPlaceholders(['id' => 1, 'name' => 'A']));

        $helper->applyWhere($qb, [
            'is_active' => true,
            'status' => UserStatus::Active,
            'id' => [1, 2],
            '$or' => [
                ['name LIKE' => 'A%'],
                ['deleted_at' => null],
            ],
        ]);

        self::assertSame(
            'SELECT * FROM users WHERE is_active = :w_is_active AND status = :w_status AND id IN (:w_id, :w_id_1) AND ((name LIKE :w_name) OR (deleted_at IS NULL))',
            $qb->getSQL(),
        );
    }

    public function testApplyWhereSkipsEmptyFilter(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $helper = new QueryHelper(TypeConverterRegistry::defaults());
        $qb = $connection->createQueryBuilder()->select('*')->from('users');

        $helper->applyWhere($qb, []);

        self::assertSame('SELECT * FROM users', $qb->getSQL());
    }

    public function testApplyWhereThrowsForInvalidOrStructure(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $helper = new QueryHelper(TypeConverterRegistry::defaults());
        $qb = $connection->createQueryBuilder()->select('*')->from('users');

        $this->expectException(\LogicException::class);
        $helper->applyWhere($qb, ['$or' => 'invalid']);
    }

    public function testApplyWhereThrowsForInvalidAndGroupItem(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $helper = new QueryHelper(TypeConverterRegistry::defaults());
        $qb = $connection->createQueryBuilder()->select('*')->from('users');

        $this->expectException(\LogicException::class);
        $helper->applyWhere($qb, ['$and' => ['bad-group']]);
    }
}
