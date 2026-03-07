<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Connection;
use PHPUnit\Framework\TestCase;

final class V2ConnectionTest extends TestCase
{
    public function testExecuteStatementAndFetchOne(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $connection->executeStatement('INSERT INTO items (id, name) VALUES (:id, :name)', ['id' => 1, 'name' => 'A']);

        self::assertSame('A', $connection->fetchOne('SELECT name FROM items WHERE id = :id', ['id' => 1]));
    }

    public function testTransactionalRollsBackOnException(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $connection->executeStatement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        try {
            $connection->transactional(function (Connection $conn): void {
                $conn->executeStatement('INSERT INTO items (id, name) VALUES (:id, :name)', ['id' => 1, 'name' => 'A']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame('0', (string) $connection->fetchOne('SELECT COUNT(*) FROM items'));
    }
}
