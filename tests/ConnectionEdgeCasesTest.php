<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Connection;
use PHPUnit\Framework\TestCase;

final class ConnectionEdgeCasesTest extends TestCase
{
    public function testStaticCreateBuildsWorkingConnection(): void
    {
        $connection = Connection::create('sqlite::memory:');
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $connection->executeStatement('INSERT INTO items (id, name) VALUES (1, "A")');

        self::assertSame('A', $connection->fetchOne('SELECT name FROM items WHERE id = 1'));
    }

    public function testFetchAssociativeReturnsFalseWithoutRows(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        self::assertFalse($connection->fetchAssociative('SELECT * FROM items WHERE id = :id', ['id' => 1]));
    }

    public function testToIterableAndLastInsertIdAndDriverName(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $connection->executeStatement('INSERT INTO items (name) VALUES (:name)', ['name' => 'A']);
        $connection->executeStatement('INSERT INTO items (name) VALUES (:name)', ['name' => 'B']);

        $rows = iterator_to_array($connection->toIterable('SELECT name FROM items ORDER BY id'));
        self::assertSame([['name' => 'A'], ['name' => 'B']], array_values($rows));
        self::assertSame('2', $connection->lastInsertId());
        self::assertSame('sqlite', $connection->getDriverName());
        self::assertInstanceOf(\PDO::class, $connection->getPdo());
    }

    public function testNestedTransactionsCommitAndRollbackAndGuards(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'), true);
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $connection->beginTransaction();
        self::assertSame(1, $connection->getTransactionNestingLevel());
        $connection->beginTransaction();
        self::assertSame(2, $connection->getTransactionNestingLevel());
        $connection->executeStatement('INSERT INTO items (id, name) VALUES (1, "A")');
        $connection->commit();
        self::assertSame(1, $connection->getTransactionNestingLevel());
        $connection->commit();
        self::assertSame(0, $connection->getTransactionNestingLevel());
        self::assertSame('1', (string) $connection->fetchOne('SELECT COUNT(*) FROM items'));

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->executeStatement('INSERT INTO items (id, name) VALUES (2, "B")');
        $connection->rollBack();
        $connection->commit();
        self::assertSame('1', (string) $connection->fetchOne('SELECT COUNT(*) FROM items'));

        try {
            $connection->commit();
            self::fail('Expected commit guard when no transaction is active.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $connection->rollBack();
    }

    public function testTransactionalReturnsResultOnSuccess(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $result = $connection->transactional(function (Connection $conn): string {
            $conn->executeStatement('INSERT INTO items (id, name) VALUES (1, "A")');

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame('1', (string) $connection->fetchOne('SELECT COUNT(*) FROM items'));
    }
}
