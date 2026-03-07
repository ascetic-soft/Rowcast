<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder;

use AsceticSoft\Rowcast\Connection;
use PHPUnit\Framework\TestCase;

final class QueryBuilderFluentExecutionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection(new \PDO('sqlite::memory:'));
        $this->connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE profiles (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, city TEXT NOT NULL)');

        $this->connection->executeStatement('INSERT INTO users (id, name, age) VALUES (1, "Alice", 30)');
        $this->connection->executeStatement('INSERT INTO users (id, name, age) VALUES (2, "Bob", 20)');
        $this->connection->executeStatement('INSERT INTO profiles (id, user_id, city) VALUES (1, 1, "Paris")');
    }

    public function testBuildsComplexSelectAndExecutesFetchers(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('u.id')
            ->addSelect('u.name')
            ->from('users', 'u')
            ->innerJoin('u', 'profiles', 'p', 'p.user_id = u.id')
            ->where('u.age >= :min_age')
            ->andWhere(['u.name LIKE' => 'A%'])
            ->groupBy('u.id')
            ->having('COUNT(p.id) >= 1')
            ->andHaving('MAX(u.age) >= 30')
            ->orderBy('u.id', 'desc')
            ->addOrderBy('u.name', 'asc')
            ->setOffset(0)
            ->setLimit(10)
            ->setParameter('min_age', 18)
        ;

        self::assertSame($this->connection, $qb->getConnection());
        self::assertSame(
            'SELECT u.id, u.name FROM users u INNER JOIN profiles p ON p.user_id = u.id WHERE u.age >= :min_age AND u.name LIKE :w_u_name GROUP BY u.id HAVING COUNT(p.id) >= 1 AND MAX(u.age) >= 30 ORDER BY u.id DESC, u.name ASC LIMIT 10',
            $qb->getSQL(),
        );
        self::assertSame([['id' => 1, 'name' => 'Alice']], $qb->fetchAllAssociative());
        self::assertSame(['id' => 1, 'name' => 'Alice'], $qb->fetchAssociative());
        self::assertSame(1, $qb->fetchOne());
    }

    public function testJoinVariantsCompile(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('u.id')
            ->from('users', 'u')
            ->join('u', 'profiles', 'p', 'p.user_id = u.id')
            ->leftJoin('u', 'profiles', 'p2', 'p2.user_id = u.id')
            ->rightJoin('u', 'profiles', 'p3', 'p3.user_id = u.id')
            ->getSQL();

        self::assertSame(
            'SELECT u.id FROM users u INNER JOIN profiles p ON p.user_id = u.id LEFT JOIN profiles p2 ON p2.user_id = u.id RIGHT JOIN profiles p3 ON p3.user_id = u.id',
            $sql,
        );
    }

    public function testInsertUpdateDeleteAndIterableExecution(): void
    {
        $inserted = $this->connection->createQueryBuilder()
            ->insert('users')
            ->values(['id' => ':id'])
            ->setValue('name', 'Charlie')
            ->setValue('age', ':age')
            ->setParameters(['id' => 3, 'name' => 'Charlie', 'age' => 41])
            ->executeStatement();

        self::assertSame(1, $inserted);

        self::assertSame(
            'UPDATE users u SET name = :name',
            $this->connection->createQueryBuilder()->update('users', 'u')->setValues(['name' => ':name'])->getSQL(),
        );

        $updated = $this->connection->createQueryBuilder()
            ->update('users')
            ->setValues(['name' => ':name'])
            ->set('age', 42)
            ->where(['id' => 3])
            ->setParameter('name', 'Charles')
            ->executeStatement();

        self::assertSame(1, $updated);

        self::assertSame(
            'DELETE FROM users u',
            $this->connection->createQueryBuilder()->delete('users', 'u')->getSQL(),
        );

        $deleted = $this->connection->createQueryBuilder()
            ->delete('users')
            ->where(['id' => 3])
            ->executeStatement();

        self::assertSame(1, $deleted);
        self::assertSame('0', (string) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE id = 3'));

        $iterableRows = iterator_to_array(
            $this->connection->createQueryBuilder()
                ->select('u.id')
                ->from('users', 'u')
                ->orderBy('u.id')
                ->toIterable(),
        );

        self::assertSame([['id' => 1], ['id' => 2]], array_values($iterableRows));
    }

    public function testExecuteQueryReturnsStatement(): void
    {
        $stmt = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['id' => 1])
            ->executeQuery();

        self::assertInstanceOf(\PDOStatement::class, $stmt);
        self::assertSame([['id' => 1]], array_values($stmt->fetchAll(\PDO::FETCH_ASSOC)));
    }

    public function testUpsertCompilesAndExecutesForSqlite(): void
    {
        $this->connection->createQueryBuilder()
            ->upsert('users')
            ->values(['id' => ':id', 'name' => ':name', 'age' => ':age'])
            ->onConflict('id')
            ->doUpdateSet(['name', 'age'])
            ->setParameters(['id' => 1, 'name' => 'Alice2', 'age' => 31])
            ->executeStatement();

        self::assertSame('Alice2', $this->connection->fetchOne('SELECT name FROM users WHERE id = 1'));
    }
}
