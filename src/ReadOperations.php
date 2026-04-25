<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

final readonly class ReadOperations
{
    public function __construct(
        private ConnectionInterface $connection,
        private Hydrator $hydrator,
        private TargetResolver $targetResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, string> $orderBy
     * @return list<object>
     */
    public function findAll(
        string|Mapping $target,
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        [$qb, $className, $mapping] = $this->buildSelectQuery($target, $where, $orderBy, $limit, $offset);
        $rows = $qb->fetchAllAssociative();

        return $this->hydrator->hydrateAll($className, $rows, $mapping);
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, string> $orderBy
     * @return iterable<int, object>
     */
    public function iterateAll(
        string|Mapping $target,
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): iterable {
        [$qb, $className, $mapping] = $this->buildSelectQuery($target, $where, $orderBy, $limit, $offset);

        return $this->hydrator->hydrateIterable($className, $qb->toIterable(), $mapping);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function findOne(string|Mapping $target, array $where = []): ?object
    {
        [$qb, $className, $mapping] = $this->buildSelectQuery($target, $where, limit: 1);
        $row = $qb->fetchAssociative();
        if ($row === false) {
            return null;
        }

        return $this->hydrator->hydrate($className, $row, $mapping);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function hydrate(string|Mapping $target, array $row): object
    {
        [, $className, $mapping] = $this->targetResolver->resolveTarget($target);

        return $this->hydrator->hydrate($className, $row, $mapping);
    }

    /**
     * @param string|Mapping $target
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    public function hydrateAll(string|Mapping $target, array $rows): array
    {
        [, $className, $mapping] = $this->targetResolver->resolveTarget($target);

        return $this->hydrator->hydrateAll($className, $rows, $mapping);
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, string> $orderBy
     * @return array{0: QueryBuilder, 1: class-string, 2: Mapping|null}
     */
    private function buildSelectQuery(
        string|Mapping $target,
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        [$table, $className, $mapping] = $this->targetResolver->resolveTarget($target);

        $qb = $this->connection->createQueryBuilder()->select('*')->from($table);
        if ($where !== []) {
            $qb->where($where);
        }

        foreach ($orderBy as $column => $direction) {
            $qb->addOrderBy($column, $direction);
        }

        if ($limit !== null) {
            $qb->setLimit($limit);
        }

        if ($offset !== null) {
            $qb->setOffset($offset);
        }

        return [$qb, $className, $mapping];
    }
}
