<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

final readonly class DataMapper
{
    private Hydrator $hydrator;
    private Extractor $extractor;
    private TargetResolver $targetResolver;
    private QueryHelper $queryHelper;

    public function __construct(
        private ConnectionInterface $connection,
        ?NameConverterInterface $nameConverter = null,
        ?TypeConverterInterface $typeConverter = null,
    ) {
        $nameConverter ??= new SnakeCaseToCamelCase();
        $typeConverter ??= TypeConverterRegistry::defaults();
        $this->hydrator = new Hydrator($typeConverter, $nameConverter);
        $this->extractor = new Extractor($nameConverter, $typeConverter);
        $this->targetResolver = new TargetResolver($nameConverter);
        $this->queryHelper = new QueryHelper($typeConverter);
    }

    public function insert(string|Mapping $target, object $dto): string|false
    {
        [$table] = $this->targetResolver->resolveTarget($target, $dto);
        $data = $this->extract($target, $dto);
        if ($data === []) {
            throw new \LogicException('Cannot insert: no data extracted from the DTO.');
        }

        $values = $this->queryHelper->createPlaceholders($data);
        $qb = $this->connection->createQueryBuilder()
            ->insert($table)
            ->values($values)
        ;
        foreach ($data as $column => $value) {
            $qb->setParameter($column, $value);
        }
        $qb->executeStatement();

        return $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $where
     */
    public function update(string|Mapping $target, object $dto, array $where): int
    {
        [$table] = $this->targetResolver->resolveTarget($target, $dto);
        $data = $this->extract($target, $dto);
        if ($data === []) {
            throw new \LogicException('Cannot update: no data extracted from the DTO.');
        }
        if ($where === []) {
            throw new \LogicException('Cannot update: WHERE conditions are required.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->update($table);

        foreach ($data as $column => $value) {
            $paramName = 'v_' . $column;
            $qb->set($column, ':' . $paramName);
            $qb->setParameter($paramName, $value);
        }

        $this->queryHelper->applyWhere($qb, $where);

        return $qb->executeStatement();
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string|Mapping $target, array $where): int
    {
        [$table] = $this->targetResolver->resolveTarget($target);
        if ($where === []) {
            throw new \LogicException('Cannot delete: WHERE conditions are required.');
        }

        $qb = $this->connection->createQueryBuilder()->delete($table);
        $this->queryHelper->applyWhere($qb, $where);

        return $qb->executeStatement();
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

        foreach ($qb->toIterable() as $row) {
            yield $this->hydrator->hydrate($className, $row, $mapping);
        }
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
     * @return array<string, mixed>
     */
    public function extract(string|Mapping $target, object $dto): array
    {
        [, , $mapping] = $this->targetResolver->resolveTarget($target, $dto);

        return $this->extractor->extract($dto, $mapping);
    }

    public function save(string|Mapping $target, object $dto, string ...$identityProperties): void
    {
        if ($identityProperties === []) {
            throw new \LogicException('Cannot save: identity properties are required.');
        }

        [$table, , $mapping] = $this->targetResolver->resolveTarget($target, $dto);
        $data = $this->extractor->extract($dto, $mapping);
        $where = $this->targetResolver->buildWhereFromIdentityProperties($identityProperties, $data, $mapping);

        $qb = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($table)
            ->setLimit(1)
        ;
        $this->queryHelper->applyWhere($qb, $where);

        if ($qb->fetchOne() === false) {
            $this->insert($target, $dto);

            return;
        }

        $this->update($target, $dto, $where);
    }

    public function upsert(string|Mapping $target, object $dto, string ...$conflictProperties): int
    {
        if ($conflictProperties === []) {
            throw new \LogicException('Cannot upsert: conflict properties are required.');
        }

        [$table, , $mapping] = $this->targetResolver->resolveTarget($target, $dto);
        $data = $this->extractor->extract($dto, $mapping);
        if ($data === []) {
            throw new \LogicException('Cannot upsert: no data extracted from the DTO.');
        }

        $conflictColumns = [];
        foreach ($conflictProperties as $propertyName) {
            $columnName = $this->targetResolver->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $data)) {
                throw new \LogicException(\sprintf('Conflict property "%s" is not extracted.', $propertyName));
            }
            $conflictColumns[] = $columnName;
        }

        $updateColumns = array_values(array_filter(
            array_keys($data),
            static fn (string $column): bool => !\in_array($column, $conflictColumns, true),
        ));

        $qb = $this->connection->createQueryBuilder()
            ->upsert($table)
            ->values($this->queryHelper->createPlaceholders($data))
            ->onConflict(...$conflictColumns)
            ->doUpdateSet($updateColumns)
        ;
        foreach ($data as $column => $value) {
            $qb->setParameter($column, $value);
        }

        return $qb->executeStatement();
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
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
        $this->queryHelper->applyWhere($qb, $where);

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
