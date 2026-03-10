<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\SqlFragments;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectFactory;
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

    public function insert(string|Mapping $target, object $dto): void
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
    }

    /**
     * @param list<object> $dtos
     */
    public function batchInsert(string|Mapping $target, array $dtos, ?int $maxBindParameters = null): void
    {
        if ($dtos === []) {
            return;
        }

        [$table, $rows] = $this->extractAll($target, $dtos, 'insert');
        $dialect = DialectFactory::fromDriverName($this->connection->getDriverName());
        $effectiveMaxBindParameters = $maxBindParameters ?? $dialect->getMaxBindParameters();

        $this->executeChunkedInsert($table, $rows, $effectiveMaxBindParameters);
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

    /**
     * @param list<object> $dtos
     * @param list<string> $conflictProperties
     */
    public function batchUpsert(
        string|Mapping $target,
        array $dtos,
        array $conflictProperties,
        ?int $maxBindParameters = null,
    ): void {
        if ($conflictProperties === []) {
            throw new \LogicException('Cannot batch upsert: conflict properties are required.');
        }

        if ($dtos === []) {
            return;
        }

        [$table, $rows, $mapping] = $this->extractAll($target, $dtos, 'upsert', includeMapping: true);
        $dialect = DialectFactory::fromDriverName($this->connection->getDriverName());
        $effectiveMaxBindParameters = $maxBindParameters ?? $dialect->getMaxBindParameters();

        $conflictColumns = [];
        foreach ($conflictProperties as $propertyName) {
            $columnName = $this->targetResolver->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $rows[0])) {
                throw new \LogicException(\sprintf('Conflict property "%s" is not extracted.', $propertyName));
            }
            $conflictColumns[] = $columnName;
        }

        $updateColumns = array_values(array_filter(
            array_keys($rows[0]),
            static fn (string $column): bool => !\in_array($column, $conflictColumns, true),
        ));

        $upsertClause = $dialect->compileUpsertClause($conflictColumns, $updateColumns);
        $this->executeChunkedInsert($table, $rows, $effectiveMaxBindParameters, $upsertClause);
    }

    /**
     * @param list<object> $dtos
     * @param list<string> $identityProperties
     */
    public function batchUpdate(
        string|Mapping $target,
        array $dtos,
        array $identityProperties,
        ?int $maxBindParameters = null,
    ): void {
        if ($identityProperties === []) {
            throw new \LogicException('Cannot batch update: identity properties are required.');
        }

        if ($dtos === []) {
            return;
        }

        [$table, $rows, $mapping] = $this->extractAll($target, $dtos, 'update', includeMapping: true);
        $dialect = DialectFactory::fromDriverName($this->connection->getDriverName());
        $effectiveMaxBindParameters = $maxBindParameters ?? $dialect->getMaxBindParameters();
        if ($effectiveMaxBindParameters < 1) {
            throw new \LogicException('maxBindParameters must be greater than zero.');
        }

        $identityColumns = [];
        foreach ($identityProperties as $propertyName) {
            $columnName = $this->targetResolver->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $rows[0])) {
                throw new \LogicException(\sprintf('Identity property "%s" is not extracted.', $propertyName));
            }
            $identityColumns[] = $columnName;
        }

        $updateColumns = array_values(array_filter(
            array_keys($rows[0]),
            static fn (string $column): bool => !\in_array($column, $identityColumns, true),
        ));
        if ($updateColumns === []) {
            throw new \LogicException('Cannot batch update: no columns left to update after excluding identity properties.');
        }

        $requiredParameters = \count($updateColumns) + \count($identityColumns);
        if ($requiredParameters > $effectiveMaxBindParameters) {
            throw new \LogicException(\sprintf(
                'Cannot batch update: statement requires %d parameters, but maxBindParameters is %d.',
                $requiredParameters,
                $effectiveMaxBindParameters,
            ));
        }

        $setParts = array_map(
            static fn (string $column): string => $column . ' = :v_' . $column,
            $updateColumns,
        );
        $whereParts = array_map(
            static fn (string $column): string => $column . ' = :w_' . $column,
            $identityColumns,
        );
        $sql = 'UPDATE ' . $table
            . ' SET ' . implode(', ', $setParts)
            . ' WHERE ' . implode(' AND ', $whereParts);

        $this->connection->transactional(function () use ($rows, $updateColumns, $identityColumns, $sql): void {
            $statement = $this->connection->getPdo()->prepare($sql);
            foreach ($rows as $index => $row) {
                $params = [];

                foreach ($updateColumns as $column) {
                    $params['v_' . $column] = $row[$column];
                }

                foreach ($identityColumns as $column) {
                    if ($row[$column] === null) {
                        throw new \LogicException(\sprintf(
                            'Cannot batch update: identity column "%s" is null at row index %d.',
                            $column,
                            $index,
                        ));
                    }
                    $params['w_' . $column] = $row[$column];
                }

                $statement->execute($params);
            }
        });
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @param list<object> $dtos
     * @return array{0: string, 1: list<array<string, mixed>>, 2: Mapping|null}
     */
    private function extractAll(
        string|Mapping $target,
        array $dtos,
        string $operation,
        bool $includeMapping = false,
    ): array {
        if ($dtos === []) {
            throw new \LogicException('Internal error: extractAll() received empty DTO list.');
        }

        [$table, , $mapping] = $this->targetResolver->resolveTarget($target, $dtos[0]);

        $rows = [];
        $expectedColumns = null;
        foreach ($dtos as $index => $dto) {
            $data = $this->extractor->extract($dto, $mapping);
            if ($data === []) {
                throw new \LogicException(\sprintf(
                    'Cannot batch %s: no data extracted from DTO at index %d.',
                    $operation,
                    $index,
                ));
            }

            $columns = array_keys($data);
            if ($expectedColumns === null) {
                $expectedColumns = $columns;
            } elseif ($columns !== $expectedColumns) {
                throw new \LogicException(\sprintf(
                    'Cannot batch %s: extracted columns mismatch at DTO index %d.',
                    $operation,
                    $index,
                ));
            }

            $rows[] = $data;
        }

        if ($includeMapping) {
            return [$table, $rows, $mapping];
        }

        return [$table, $rows, $mapping];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function executeChunkedInsert(string $table, array $rows, int $maxBindParameters, string $suffix = ''): void
    {
        if ($maxBindParameters < 1) {
            throw new \LogicException('maxBindParameters must be greater than zero.');
        }

        $columns = array_keys($rows[0]);
        $columnCount = \count($columns);
        if ($columnCount > $maxBindParameters) {
            throw new \LogicException(\sprintf(
                'Cannot execute batch insert: %d columns exceed max bind parameters %d.',
                $columnCount,
                $maxBindParameters,
            ));
        }

        $chunkSize = max(1, intdiv($maxBindParameters, $columnCount));

        $this->connection->transactional(function () use ($table, $rows, $columns, $chunkSize, $suffix): void {
            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $sql = SqlFragments::buildMultiRowInsertSql($table, $columns, \count($chunk)) . $suffix;

                $params = [];
                foreach ($chunk as $rowIndex => $row) {
                    foreach ($columns as $column) {
                        $params[$column . '_' . $rowIndex] = $row[$column];
                    }
                }

                $this->connection->executeStatement($sql, $params);
            }
        });
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
