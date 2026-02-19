<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

interface ConnectionInterface
{
    public function createQueryBuilder(): QueryBuilder;

    /**
     * @param array<string|int, mixed> $params
     */
    public function executeQuery(string $sql, array $params = []): \PDOStatement;

    /**
     * @param array<string|int, mixed> $params
     */
    public function executeStatement(string $sql, array $params = []): int;

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(string $sql, array $params = []): array;

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(string $sql, array $params = []): array|false;

    /**
     * @param array<string|int, mixed> $params
     */
    public function fetchOne(string $sql, array $params = []): mixed;

    public function lastInsertId(?string $name = null): string|false;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    /**
     * @template T
     * @param callable(static): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed;

    /**
     * @param array<string|int, mixed> $params
     * @return iterable<int, array<string, mixed>>
     */
    public function toIterable(string $sql, array $params = []): iterable;

    public function getTransactionNestingLevel(): int;

    public function getDriverName(): string;

    public function getPdo(): \PDO;
}
