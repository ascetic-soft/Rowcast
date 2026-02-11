<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

/**
 * Thin wrapper around PDO providing convenience methods for query execution
 * and a factory for QueryBuilder instances.
 */
final readonly class Connection
{
    public function __construct(
        private \PDO $pdo,
    ) {
        // Ensure PDO throws exceptions on errors — required for safe operation
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Creates a new Connection from DSN parameters.
     *
     * @param array<int, mixed> $options PDO driver options
     */
    public static function create(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
    ): self {
        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username, $password, array_replace($defaultOptions, $options));

        return new self($pdo);
    }

    /**
     * Creates a new QueryBuilder bound to this connection.
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    /**
     * Executes an SQL query (SELECT) and returns the resulting statement.
     *
     * @param array<string|int, mixed> $params Positional (?) or named (:name) parameters
     */
    public function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Executes an SQL statement (INSERT, UPDATE, DELETE) and returns the number of affected rows.
     *
     * @param array<string|int, mixed> $params Positional (?) or named (:name) parameters
     */
    public function executeStatement(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Executes a query and returns all rows as associative arrays.
     *
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(string $sql, array $params = []): array
    {
        $stmt = $this->executeQuery($sql, $params);

        return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Executes a query and returns the first row as an associative array,
     * or false if no rows are found.
     *
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(string $sql, array $params = []): array|false
    {
        $stmt = $this->executeQuery($sql, $params);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (\is_array($result)) {
            /** @var array<string, mixed> $result */
            return $result;
        }

        return false;
    }

    /**
     * Executes a query and returns the value of the first column of the first row,
     * or false if no rows are found.
     *
     * @param array<string|int, mixed> $params
     */
    public function fetchOne(string $sql, array $params = []): mixed
    {
        return $this->executeQuery($sql, $params)->fetchColumn();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Starts a database transaction.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commits the current transaction.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rolls back the current transaction.
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Executes a callback within a transaction.
     *
     * If the callback completes without throwing, the transaction is committed.
     * If the callback throws, the transaction is rolled back and the exception is re-thrown.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    /**
     * Executes a query and returns an iterable that yields rows one at a time.
     *
     * Uses PDO cursor-based fetching for memory-efficient iteration over large result sets:
     * - MySQL: unbuffered queries (PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false)
     * - PostgreSQL: scrollable cursor (PDO::ATTR_CURSOR = PDO::CURSOR_SCROLL)
     * - Other drivers: regular sequential fetch
     *
     * @param array<string|int, mixed> $params Positional (?) or named (:name) parameters
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function toIterable(string $sql, array $params = []): iterable
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $restoreBuffered = false;

        try {
            if ($driver === 'mysql') {
                $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $restoreBuffered = true;
                $stmt = $this->pdo->prepare($sql);
            } elseif ($driver === 'pgsql') {
                $stmt = $this->pdo->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);
            } else {
                $stmt = $this->pdo->prepare($sql);
            }

            $stmt->execute($params);

            try {
                while (false !== ($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
                    /** @var array<string, mixed> $row */
                    yield $row;
                }
            } finally {
                $stmt->closeCursor();
            }
        } finally {
            if ($restoreBuffered) {
                $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        }
    }

    /**
     * Returns the underlying PDO instance.
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
