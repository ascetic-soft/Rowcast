<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder;

use AsceticSoft\Rowcast\Connection;
use PHPUnit\Framework\TestCase;

final class QueryBuilderEdgeCasesTest extends TestCase
{
    public function testGetSqlThrowsWhenQueryTypeIsMissing(): void
    {
        $qb = new Connection(new \PDO('sqlite::memory:'))->createQueryBuilder();

        $this->expectException(\LogicException::class);
        $qb->getSQL();
    }

    public function testWhereOperatorValidationErrors(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $this->expectException(\LogicException::class);
        $connection->createQueryBuilder()->select('*')->from('users')->where(['id IN' => 1])->getSQL();
    }

    public function testWhereBetweenValidationError(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $this->expectException(\LogicException::class);
        $connection->createQueryBuilder()->select('*')->from('users')->where(['age BETWEEN' => [18]])->getSQL();
    }

    public function testWhereUnsupportedOperatorValidationError(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $this->expectException(\LogicException::class);
        $connection->createQueryBuilder()->select('*')->from('users')->where(['age ~~' => 10])->getSQL();
    }

    public function testWhereOrAndValidationErrors(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        try {
            $connection->createQueryBuilder()->select('*')->from('users')->where(['$or' => 'bad'])->getSQL();
            self::fail('Expected LogicException for invalid $or value.');
        } catch (\LogicException) {
        }

        try {
            $connection->createQueryBuilder()->select('*')->from('users')->where(['$and' => 'bad'])->getSQL();
            self::fail('Expected LogicException for invalid $and value.');
        } catch (\LogicException) {
        }

        try {
            $connection->createQueryBuilder()->select('*')->from('users')->where(['$or' => ['bad-group']])->getSQL();
            self::fail('Expected LogicException for invalid $or group item.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $connection->createQueryBuilder()->select('*')->from('users')->where(['$and' => ['bad-group']])->getSQL();
    }

    public function testWhereNotInEmptyArrayCompilesToAlwaysTrue(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $sql = $connection->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where(['id !=' => []])
            ->getSQL();

        self::assertSame('SELECT * FROM users WHERE 1 = 1', $sql);
    }

    public function testOrWhereWorksWithoutInitialWhereAndSkipsEmptyPredicate(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sqlSingle = $connection->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->orWhere(['id' => 1])
            ->getSQL();
        self::assertSame('SELECT * FROM users WHERE id = :w_id', $sqlSingle);

        $sqlSkipped = $connection->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where(['id' => 1])
            ->orWhere([])
            ->getSQL();
        self::assertSame('SELECT * FROM users WHERE id = :w_id', $sqlSkipped);
    }

    public function testEmptyNestedGroupsAreIgnored(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where(['$or' => []])
            ->andWhere(['$and' => [['id' => 1]]])
            ->getSQL();

        self::assertSame('SELECT * FROM users WHERE  AND id = :w_id', $sql);
    }
}
