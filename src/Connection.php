<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

/**
 * Thin wrapper around PDO providing convenience methods for query execution
 * and a factory for QueryBuilder instances.
 */
final class Connection implements ConnectionInterface
{
    private int $transactionNestingLevel = 0;
    private readonly TypeConverterInterface $typeConverter;

    /** @var array<string, \PDOStatement> */
    private array $statementCache = [];

    /**
     * @param bool $nestTransactions When true, nested beginTransaction()/commit()/rollBack()
     *                               calls use SQL SAVEPOINTs instead of failing.
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly bool $nestTransactions = false,
        ?TypeConverterInterface $typeConverter = null,
    ) {
        // Ensure PDO throws exceptions on errors — required for safe operation
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->typeConverter = $typeConverter ?? TypeConverterRegistry::defaults();
    }

    /**
     * Creates a new Connection from DSN parameters.
     *
     * @param array<int, mixed> $options PDO driver options
     * @param bool $nestTransactions Enable savepoint-based nested transactions
     */
    public static function create(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
        bool $nestTransactions = false,
        ?TypeConverterInterface $typeConverter = null,
    ): self {
        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $pdo = new \PDO($dsn, $username, $password, array_replace($defaultOptions, $options));

        return new self($pdo, $nestTransactions, $typeConverter);
    }

    /**
     * Creates a new QueryBuilder bound to this connection.
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this, $this->typeConverter);
    }

    /**
     * Executes an SQL query (SELECT) and returns the resulting statement.
     *
     * @param array<string|int, mixed> $params Positional (?) or named (:name) parameters
     */
    public function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        return $this->prepareAndExecute($sql, $params, reuseStatement: false);
    }

    /**
     * Executes an SQL statement (INSERT, UPDATE, DELETE) and returns the number of affected rows.
     *
     * @param array<string|int, mixed> $params Positional (?) or named (:name) parameters
     */
    public function executeStatement(string $sql, array $params = []): int
    {
        $stmt = $this->prepareAndExecute($sql, $params, reuseStatement: true);

        return $stmt->rowCount();
    }

    /**
     * @param array<string|int, mixed> $params
     */
    private function prepareAndExecute(string $sql, array $params = [], bool $reuseStatement = false): \PDOStatement
    {
        $stmt = $reuseStatement
            ? ($this->statementCache[$sql] ??= $this->pdo->prepare($sql))
            : $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Executes a query and returns all rows as associative arrays.
     *
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $params, reuseStatement: true);

        try {
            return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } finally {
            $stmt->closeCursor();
        }
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
        $stmt = $this->prepareAndExecute($sql, $params, reuseStatement: true);

        try {
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        } finally {
            $stmt->closeCursor();
        }

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
        $stmt = $this->prepareAndExecute($sql, $params, reuseStatement: true);

        try {
            return $stmt->fetchColumn();
        } finally {
            $stmt->closeCursor();
        }
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
     *
     * When nested transactions are enabled, inner calls create SAVEPOINTs
     * instead of starting a real transaction.
     */
    public function beginTransaction(): void
    {
        if (!$this->nestTransactions) {
            $this->pdo->beginTransaction();

            return;
        }

        $this->beginNestedTransaction();

        ++$this->transactionNestingLevel;
    }

    /**
     * Commits the current transaction.
     *
     * When nested transactions are enabled, inner calls release the corresponding SAVEPOINT.
     */
    public function commit(): void
    {
        if (!$this->nestTransactions) {
            $this->pdo->commit();

            return;
        }

        $this->assertNestedTransactionIsActive();
        $this->commitNestedTransaction();

        --$this->transactionNestingLevel;
    }

    /**
     * Rolls back the current transaction.
     *
     * When nested transactions are enabled, inner calls roll back to the corresponding SAVEPOINT.
     */
    public function rollBack(): void
    {
        if (!$this->nestTransactions) {
            $this->pdo->rollBack();

            return;
        }

        $this->assertNestedTransactionIsActive();
        $this->rollBackNestedTransaction();

        --$this->transactionNestingLevel;
    }

    /**
     * Returns the current transaction nesting depth.
     *
     * Always returns 0 when nested transactions are disabled.
     */
    public function getTransactionNestingLevel(): int
    {
        return $this->transactionNestingLevel;
    }

    /**
     * Executes a callback within a transaction.
     *
     * If the callback completes without throwing, the transaction is committed.
     * If the callback throws, the transaction is rolled back and the exception is re-thrown.
     *
     * When nested transactions are enabled, inner transactional() calls use SAVEPOINTs,
     * so a failure in the inner callback rolls back only to the savepoint, not the entire transaction.
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
        $restoreBuffered = false;

        try {
            $stmt = $this->prepareIterableStatement($sql, $restoreBuffered);
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

    private function beginNestedTransaction(): void
    {
        if ($this->transactionNestingLevel === 0) {
            $this->pdo->beginTransaction();

            return;
        }

        $this->pdo->exec('SAVEPOINT ' . $this->getSavepointName($this->transactionNestingLevel));
    }

    private function commitNestedTransaction(): void
    {
        if ($this->transactionNestingLevel === 1) {
            $this->pdo->commit();

            return;
        }

        $this->pdo->exec('RELEASE SAVEPOINT ' . $this->getSavepointName($this->transactionNestingLevel - 1));
    }

    private function rollBackNestedTransaction(): void
    {
        if ($this->transactionNestingLevel === 1) {
            $this->pdo->rollBack();

            return;
        }

        $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $this->getSavepointName($this->transactionNestingLevel - 1));
    }

    private function assertNestedTransactionIsActive(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw new \LogicException('No active transaction.');
        }
    }

    private function getSavepointName(int $level): string
    {
        return 'ROWCAST_' . $level;
    }

    private function prepareIterableStatement(string $sql, bool &$restoreBuffered): \PDOStatement
    {
        $driver = $this->getDriverName();

        if ($driver === 'mysql') {
            // @codeCoverageIgnoreStart
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $restoreBuffered = true;

            return $this->pdo->prepare($sql);
            // @codeCoverageIgnoreEnd
        }

        if ($driver === 'pgsql') {
            // @codeCoverageIgnoreStart
            return $this->pdo->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);
            // @codeCoverageIgnoreEnd
        }

        return $this->pdo->prepare($sql);
    }

    public function getDriverName(): string
    {
        /** @var string $driver */
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return $driver;
    }

    /**
     * Returns the underlying PDO instance.
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

}
