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
    private NameConverterInterface $nameConverter;
    private TypeConverterInterface $typeConverter;
    private Hydrator $hydrator;
    private Extractor $extractor;

    public function __construct(
        private ConnectionInterface $connection,
        ?NameConverterInterface $nameConverter = null,
        ?TypeConverterInterface $typeConverter = null,
    ) {
        $this->nameConverter = $nameConverter ?? new SnakeCaseToCamelCase();
        $this->typeConverter = $typeConverter ?? TypeConverterRegistry::defaults();
        $this->hydrator = new Hydrator($this->typeConverter, $this->nameConverter);
        $this->extractor = new Extractor($this->nameConverter, $this->typeConverter);
    }

    public function insert(string|Mapping $target, object $dto): string|false
    {
        [$table] = $this->resolveTarget($target, $dto);
        $data = $this->extract($target, $dto);
        if ($data === []) {
            throw new \LogicException('Cannot insert: no data extracted from the DTO.');
        }

        $values = $this->createPlaceholders($data);
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
        [$table] = $this->resolveTarget($target, $dto);
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

        $this->applyWhere($qb, $where, 'w_');

        return $qb->executeStatement();
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string|Mapping $target, array $where): int
    {
        [$table] = $this->resolveTarget($target);
        if ($where === []) {
            throw new \LogicException('Cannot delete: WHERE conditions are required.');
        }

        $qb = $this->connection->createQueryBuilder()->delete($table);
        $this->applyWhere($qb, $where);

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
        [, $className, $mapping] = $this->resolveTarget($target);

        return $this->hydrator->hydrate($className, $row, $mapping);
    }

    /**
     * @param string|Mapping $target
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    public function hydrateAll(string|Mapping $target, array $rows): array
    {
        [, $className, $mapping] = $this->resolveTarget($target);

        return $this->hydrator->hydrateAll($className, $rows, $mapping);
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(string|Mapping $target, object $dto): array
    {
        [, , $mapping] = $this->resolveTarget($target, $dto);

        return $this->extractor->extract($dto, $mapping);
    }

    public function save(string|Mapping $target, object $dto, string ...$identityProperties): void
    {
        if ($identityProperties === []) {
            throw new \LogicException('Cannot save: identity properties are required.');
        }

        [$table, , $mapping] = $this->resolveTarget($target, $dto);
        $data = $this->extractor->extract($dto, $mapping);
        $where = $this->buildWhereFromIdentityProperties($identityProperties, $data, $mapping);

        $qb = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($table)
            ->setMaxResults(1)
        ;
        $this->applyWhere($qb, $where, 'w_');

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

        [$table, , $mapping] = $this->resolveTarget($target, $dto);
        $data = $this->extractor->extract($dto, $mapping);
        if ($data === []) {
            throw new \LogicException('Cannot upsert: no data extracted from the DTO.');
        }

        $conflictColumns = [];
        foreach ($conflictProperties as $propertyName) {
            $columnName = $this->resolveColumnName($propertyName, $mapping);
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
            ->values($this->createPlaceholders($data))
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
        [$table, $className, $mapping] = $this->resolveTarget($target);

        $qb = $this->connection->createQueryBuilder()->select('*')->from($table);
        $this->applyWhere($qb, $where);

        foreach ($orderBy as $column => $direction) {
            $qb->addOrderBy($column, $direction);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return [$qb, $className, $mapping];
    }

    /**
     * @return array{0: string, 1: class-string, 2: Mapping|null}
     */
    private function resolveTarget(string|Mapping $target, ?object $dto = null): array
    {
        if ($target instanceof Mapping) {
            /** @var class-string $className */
            $className = $target->getClassName();

            return [$target->getTable(), $className, $target];
        }

        if (class_exists($target)) {
            /** @var class-string $target */
            return [$this->deriveTableName($target), $target, null];
        }

        if ($dto === null) {
            throw new \LogicException(\sprintf('Unknown class-string target "%s".', $target));
        }

        $className = $dto::class;

        return [$target, $className, null];
    }

    /**
     * @param class-string $className
     */
    private function deriveTableName(string $className): string
    {
        $shortName = new \ReflectionClass($className)->getShortName();
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return strtolower($replaced ?? $shortName) . 's';
    }

    /**
     * @param array<string, mixed> $where
     */
    private function applyWhere(QueryBuilder $qb, array $where, string $paramPrefix = ''): void
    {
        if ($where === []) {
            return;
        }

        $first = true;
        foreach ($where as $column => $value) {
            $paramName = $paramPrefix . $column;
            $predicate = $column . ' = :' . $paramName;

            if ($first) {
                $qb->where($predicate);
                $first = false;
            } else {
                $qb->andWhere($predicate);
            }

            $qb->setParameter($paramName, $this->typeConverter->toDb($value));
        }
    }

    /**
     * @param array<int|string, string> $identityProperties
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildWhereFromIdentityProperties(array $identityProperties, array $data, ?Mapping $mapping): array
    {
        $where = [];

        foreach ($identityProperties as $propertyName) {
            $columnName = $this->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $data)) {
                throw new \LogicException(\sprintf('Identity property "%s" is not extracted.', $propertyName));
            }
            $where[$columnName] = $data[$columnName];
        }

        return $where;
    }

    private function resolveColumnName(string $propertyName, ?Mapping $mapping): string
    {
        return $mapping?->getColumnForProperty($propertyName) ?? $this->nameConverter->toColumnName($propertyName);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function createPlaceholders(array $data): array
    {
        $values = [];
        foreach (array_keys($data) as $column) {
            $values[$column] = ':' . $column;
        }

        return $values;
    }
}
