<?php

declare(strict_types=1);

namespace Rowcast\Tests;

use PHPUnit\Framework\TestCase;
use Rowcast\Connection;
use Rowcast\QueryBuilder\QueryBuilder;

final class ConnectionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection(new \PDO('sqlite::memory:'));
    }

    public function testConstructorEnforcesExceptionErrorMode(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        new Connection($pdo);

        $this->assertSame(
            \PDO::ERRMODE_EXCEPTION,
            $pdo->getAttribute(\PDO::ATTR_ERRMODE),
        );
    }

    public function testCreateFactoryMethod(): void
    {
        $connection = Connection::create('sqlite::memory:');

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testCreateFactorySetsFetchModeAssoc(): void
    {
        $connection = Connection::create('sqlite::memory:');

        $this->assertSame(
            \PDO::FETCH_ASSOC,
            $connection->getPdo()->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }

    public function testGetPdoReturnsSameInstance(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $connection = new Connection($pdo);

        $this->assertSame($pdo, $connection->getPdo());
    }

    public function testCreateQueryBuilderReturnsQueryBuilder(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }

    public function testCreateQueryBuilderReturnsNewInstanceEachTime(): void
    {
        $qb1 = $this->connection->createQueryBuilder();
        $qb2 = $this->connection->createQueryBuilder();

        $this->assertNotSame($qb1, $qb2);
    }

    public function testCreateQueryBuilderBindsConnection(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertSame($this->connection, $qb->getConnection());
    }

    // -- executeQuery / executeStatement --

    public function testExecuteQuery(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $stmt = $this->connection->executeQuery(
            'SELECT name FROM users WHERE email = ?',
            ['alice@example.com'],
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Alice', $row['name']);
    }

    public function testExecuteQueryWithNamedParams(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $stmt = $this->connection->executeQuery(
            'SELECT name FROM users WHERE email = :email',
            ['email' => 'alice@example.com'],
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Alice', $row['name']);
    }

    public function testExecuteStatementReturnsAffectedRows(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $affected = $this->connection->executeStatement(
            'UPDATE users SET name = ? WHERE email = ?',
            ['Updated', 'alice@example.com'],
        );

        $this->assertSame(1, $affected);
    }

    // -- fetch helpers --

    public function testFetchAllAssociative(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->fetchAllAssociative('SELECT name FROM users ORDER BY name');

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    public function testFetchAllAssociativeEmpty(): void
    {
        $this->createUsersTable();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM users');

        $this->assertSame([], $rows);
    }

    public function testFetchAssociative(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $row = $this->connection->fetchAssociative(
            'SELECT name, email FROM users WHERE name = ?',
            ['Alice'],
        );

        $this->assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $row);
    }

    public function testFetchAssociativeReturnsFalseWhenNoRows(): void
    {
        $this->createUsersTable();

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE name = ?',
            ['Nobody'],
        );

        $this->assertFalse($row);
    }

    public function testFetchOne(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $name = $this->connection->fetchOne(
            'SELECT name FROM users WHERE email = ?',
            ['alice@example.com'],
        );

        $this->assertSame('Alice', $name);
    }

    public function testFetchOneReturnsFalseWhenNoRows(): void
    {
        $this->createUsersTable();

        $result = $this->connection->fetchOne(
            'SELECT name FROM users WHERE email = ?',
            ['nobody@example.com'],
        );

        $this->assertFalse($result);
    }

    // -- lastInsertId --

    public function testLastInsertId(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $id = $this->connection->lastInsertId();

        $this->assertNotFalse($id);
        $this->assertSame('1', $id);
    }

    public function testLastInsertIdIncrementsAfterSecondInsert(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $id = $this->connection->lastInsertId();

        $this->assertSame('2', $id);
    }

    // -- transactions --

    public function testBeginTransactionAndCommit(): void
    {
        $this->createUsersTable();

        $this->connection->beginTransaction();
        $this->insertUser('Alice', 'alice@example.com');
        $this->connection->commit();

        $this->assertSame('Alice', $this->connection->fetchOne('SELECT name FROM users'));
    }

    public function testBeginTransactionAndRollBack(): void
    {
        $this->createUsersTable();

        $this->connection->beginTransaction();
        $this->insertUser('Alice', 'alice@example.com');
        $this->connection->rollBack();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $this->assertSame(0, (int) $count);
    }

    public function testTransactionalCommitsOnSuccess(): void
    {
        $this->createUsersTable();

        $result = $this->connection->transactional(function (Connection $conn): string {
            $conn->executeStatement(
                'INSERT INTO users (name, email) VALUES (?, ?)',
                ['Alice', 'alice@example.com'],
            );

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame('Alice', $this->connection->fetchOne('SELECT name FROM users'));
    }

    public function testTransactionalRollsBackOnException(): void
    {
        $this->createUsersTable();

        try {
            $this->connection->transactional(function (Connection $conn): void {
                $conn->executeStatement(
                    'INSERT INTO users (name, email) VALUES (?, ?)',
                    ['Alice', 'alice@example.com'],
                );

                throw new \RuntimeException('Something went wrong');
            });
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Something went wrong', $e->getMessage());
        }

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $this->assertSame(0, (int) $count);
    }

    public function testTransactionalRethrowsException(): void
    {
        $this->createUsersTable();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');

        $this->connection->transactional(function (): never {
            throw new \RuntimeException('test error');
        });
    }

    // -- create factory with custom options --

    public function testCreateFactoryWithCustomOptions(): void
    {
        $connection = Connection::create('sqlite::memory:', null, null, [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_NUM,
        ]);

        // Custom option should override the default
        $this->assertSame(
            \PDO::FETCH_NUM,
            $connection->getPdo()->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }

    public function testCreateFactoryKeepsDefaultsWhenNoCustomOptions(): void
    {
        $connection = Connection::create('sqlite::memory:');

        $this->assertSame(
            \PDO::ERRMODE_EXCEPTION,
            $connection->getPdo()->getAttribute(\PDO::ATTR_ERRMODE),
        );
    }

    // -- executeQuery with empty params --

    public function testExecuteQueryWithEmptyParams(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $stmt = $this->connection->executeQuery('SELECT name FROM users');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Alice', $row['name']);
    }

    // -- executeStatement returns zero for no matches --

    public function testExecuteStatementReturnsZeroForNoMatch(): void
    {
        $this->createUsersTable();

        $affected = $this->connection->executeStatement(
            'UPDATE users SET name = ? WHERE id = ?',
            ['Nobody', 999],
        );

        $this->assertSame(0, $affected);
    }

    // -- fetchAllAssociative with params --

    public function testFetchAllAssociativeWithParams(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT name FROM users WHERE name = ?',
            ['Alice'],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testFetchAllAssociativeWithNamedParams(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT name FROM users WHERE email = :email',
            ['email' => 'bob@example.com'],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
    }

    // -- fetchOne with scalar values --

    public function testFetchOneCountQuery(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users');

        $this->assertSame(2, (int) $count);
    }

    // -- transactional nesting ---

    public function testTransactionalReturnsCallbackResult(): void
    {
        $this->createUsersTable();

        $result = $this->connection->transactional(function (): int {
            return 42;
        });

        $this->assertSame(42, $result);
    }

    public function testTransactionalWithNullReturn(): void
    {
        $this->createUsersTable();

        $result = $this->connection->transactional(function (Connection $conn): void {
            $conn->executeStatement(
                'INSERT INTO users (name, email) VALUES (?, ?)',
                ['Alice', 'alice@example.com'],
            );
        });

        $this->assertNull($result);
        $this->assertSame('Alice', $this->connection->fetchOne('SELECT name FROM users'));
    }

    // -- Multiple operations in sequence --

    public function testMultipleInsertsAndFetches(): void
    {
        $this->createUsersTable();

        for ($i = 1; $i <= 5; $i++) {
            $this->insertUser("User{$i}", "user{$i}@example.com");
        }

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM users ORDER BY id');

        $this->assertCount(5, $rows);
        $this->assertSame('User1', $rows[0]['name']);
        $this->assertSame('User5', $rows[4]['name']);
    }

    // -- Prepared statement reuse --

    public function testExecuteQueryMultipleTimes(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $name1 = $this->connection->fetchOne('SELECT name FROM users WHERE id = ?', [1]);
        $name2 = $this->connection->fetchOne('SELECT name FROM users WHERE id = ?', [2]);

        $this->assertSame('Alice', $name1);
        $this->assertSame('Bob', $name2);
    }

    // -- helpers --

    private function createUsersTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)',
        );
    }

    private function insertUser(string $name, string $email): void
    {
        $this->connection->executeStatement(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            [$name, $email],
        );
    }
}
