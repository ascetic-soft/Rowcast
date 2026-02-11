<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use PHPUnit\Framework\TestCase;
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

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

        static::assertSame(
            \PDO::ERRMODE_EXCEPTION,
            $pdo->getAttribute(\PDO::ATTR_ERRMODE),
        );
    }

    public function testCreateFactoryMethod(): void
    {
        $connection = Connection::create('sqlite::memory:');

        self::assertInstanceOf(Connection::class, $connection);
    }

    public function testCreateFactorySetsFetchModeAssoc(): void
    {
        $connection = Connection::create('sqlite::memory:');

        self::assertSame(
            \PDO::FETCH_ASSOC,
            $connection->getPdo()->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }

    public function testGetPdoReturnsSameInstance(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $connection = new Connection($pdo);

        static::assertSame($pdo, $connection->getPdo());
    }

    public function testCreateQueryBuilderReturnsQueryBuilder(): void
    {
        $qb = $this->connection->createQueryBuilder();

        static::assertInstanceOf(QueryBuilder::class, $qb);
    }

    public function testCreateQueryBuilderReturnsNewInstanceEachTime(): void
    {
        $qb1 = $this->connection->createQueryBuilder();
        $qb2 = $this->connection->createQueryBuilder();

        static::assertNotSame($qb1, $qb2);
    }

    public function testCreateQueryBuilderBindsConnection(): void
    {
        $qb = $this->connection->createQueryBuilder();

        static::assertSame($this->connection, $qb->getConnection());
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

        static::assertSame('Alice', $row['name']);
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

        static::assertSame('Alice', $row['name']);
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

        static::assertSame(1, $affected);
    }

    // -- fetch helpers --

    public function testFetchAllAssociative(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->fetchAllAssociative('SELECT name FROM users ORDER BY name');

        static::assertCount(2, $rows);
        static::assertSame('Alice', $rows[0]['name']);
        static::assertSame('Bob', $rows[1]['name']);
    }

    public function testFetchAllAssociativeEmpty(): void
    {
        $this->createUsersTable();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM users');

        static::assertSame([], $rows);
    }

    public function testFetchAssociative(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $row = $this->connection->fetchAssociative(
            'SELECT name, email FROM users WHERE name = ?',
            ['Alice'],
        );

        static::assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $row);
    }

    public function testFetchAssociativeReturnsFalseWhenNoRows(): void
    {
        $this->createUsersTable();

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE name = ?',
            ['Nobody'],
        );

        self::assertFalse($row);
    }

    public function testFetchOne(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $name = $this->connection->fetchOne(
            'SELECT name FROM users WHERE email = ?',
            ['alice@example.com'],
        );

        self::assertSame('Alice', $name);
    }

    public function testFetchOneReturnsFalseWhenNoRows(): void
    {
        $this->createUsersTable();

        $result = $this->connection->fetchOne(
            'SELECT name FROM users WHERE email = ?',
            ['nobody@example.com'],
        );

        self::assertFalse($result);
    }

    // -- lastInsertId --

    public function testLastInsertId(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $id = $this->connection->lastInsertId();

        self::assertNotFalse($id);
        self::assertSame('1', $id);
    }

    public function testLastInsertIdIncrementsAfterSecondInsert(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $id = $this->connection->lastInsertId();

        self::assertSame('2', $id);
    }

    // -- transactions --

    public function testBeginTransactionAndCommit(): void
    {
        $this->createUsersTable();

        $this->connection->beginTransaction();
        $this->insertUser('Alice', 'alice@example.com');
        $this->connection->commit();

        self::assertSame('Alice', $this->connection->fetchOne('SELECT name FROM users'));
    }

    public function testBeginTransactionAndRollBack(): void
    {
        $this->createUsersTable();

        $this->connection->beginTransaction();
        $this->insertUser('Alice', 'alice@example.com');
        $this->connection->rollBack();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        self::assertSame(0, (int) $count);
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

        self::assertSame('done', $result);
        self::assertSame('Alice', $this->connection->fetchOne('SELECT name FROM users'));
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
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertSame('Something went wrong', $e->getMessage());
        }

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        self::assertSame(0, (int) $count);
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
        self::assertSame(
            \PDO::FETCH_NUM,
            $connection->getPdo()->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }

    public function testCreateFactoryKeepsDefaultsWhenNoCustomOptions(): void
    {
        $connection = Connection::create('sqlite::memory:');

        static::assertSame(
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

        self::assertSame('Alice', $row['name']);
    }

    // -- executeStatement returns zero for no matches --

    public function testExecuteStatementReturnsZeroForNoMatch(): void
    {
        $this->createUsersTable();

        $affected = $this->connection->executeStatement(
            'UPDATE users SET name = ? WHERE id = ?',
            ['Nobody', 999],
        );

        self::assertSame(0, $affected);
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

        self::assertCount(1, $rows);
        self::assertSame('Alice', $rows[0]['name']);
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

        self::assertCount(1, $rows);
        self::assertSame('Bob', $rows[0]['name']);
    }

    // -- fetchOne with scalar values --

    public function testFetchOneCountQuery(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users');

        self::assertSame(2, (int) $count);
    }

    // -- transactional nesting ---

    public function testTransactionalReturnsCallbackResult(): void
    {
        $this->createUsersTable();

        $result = $this->connection->transactional(fn (): int => 42);

        self::assertSame(42, $result);
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

        self::assertNull($result);
        self::assertSame('Alice', $this->connection->fetchOne('SELECT name FROM users'));
    }

    // -- Multiple operations in sequence --

    public function testMultipleInsertsAndFetches(): void
    {
        $this->createUsersTable();

        for ($i = 1; $i <= 5; $i++) {
            $this->insertUser("User{$i}", "user{$i}@example.com");
        }

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM users ORDER BY id');

        self::assertCount(5, $rows);
        self::assertSame('User1', $rows[0]['name']);
        self::assertSame('User5', $rows[4]['name']);
    }

    // -- Prepared statement reuse --

    public function testExecuteQueryMultipleTimes(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $name1 = $this->connection->fetchOne('SELECT name FROM users WHERE id = ?', [1]);
        $name2 = $this->connection->fetchOne('SELECT name FROM users WHERE id = ?', [2]);

        self::assertSame('Alice', $name1);
        self::assertSame('Bob', $name2);
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
