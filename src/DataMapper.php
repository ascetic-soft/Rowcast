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
        ?Hydrator $hydrator = null,
        ?Extractor $extractor = null,
        ?TargetResolver $targetResolver = null,
        ?QueryHelper $queryHelper = null,
    ) {
        $nameConverter ??= new SnakeCaseToCamelCase();
        $typeConverter ??= TypeConverterRegistry::defaults();
        $this->hydrator = $hydrator ?? new Hydrator($typeConverter, $nameConverter);
        $this->extractor = $extractor ?? new Extractor($nameConverter, $typeConverter);
        $this->targetResolver = $targetResolver ?? new TargetResolver($nameConverter);
        $this->queryHelper = $queryHelper ?? new QueryHelper();
    }

    public function insert(string|Mapping $target, object $dto): void
    {
        [$table, $data] = $this->prepareWrite($target, $dto, 'insert');

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
        $effectiveMaxBindParameters = $this->resolveMaxBindParameters($maxBindParameters);

        $this->executeChunkedInsert($table, $rows, $effectiveMaxBindParameters);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function update(string|Mapping $target, object $dto, array $where): int
    {
        [$table, $data] = $this->prepareWrite($target, $dto, 'update');
        if ($where === []) {
            throw new \LogicException('Cannot update: WHERE conditions are required.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->update($table);

        foreach ($data as $column => $value) {
            $paramName = QueryBuilder::PARAM_PREFIX_SET . $column;
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

        [$table, $data, $mapping] = $this->prepareResolvedWrite($target, $dto, allowEmptyData: true);
        $where = $this->buildIdentityWhere($identityProperties, $data, $mapping);

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

        [$table, $data, $mapping] = $this->prepareResolvedWrite($target, $dto, 'upsert');

        $conflictColumns = $this->resolveColumns($conflictProperties, $data, $mapping, 'Conflict');

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

        [$table, $rows, $mapping] = $this->extractAll($target, $dtos, 'upsert');
        $dialect = DialectFactory::fromDriverName($this->connection->getDriverName());
        if (!$dialect->supportsUpsert()) {
            throw new \LogicException('Cannot batch upsert: UPSERT is not supported by the current database driver.');
        }
        $effectiveMaxBindParameters = $this->resolveMaxBindParameters($maxBindParameters, $dialect->getMaxBindParameters());

        $conflictColumns = $this->resolveColumns($conflictProperties, $rows[0], $mapping, 'Conflict');
        $updateColumns = $this->resolveNonKeyColumns(array_keys($rows[0]), $conflictColumns);

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

        [$table, $rows, $mapping] = $this->extractAll($target, $dtos, 'update');
        $effectiveMaxBindParameters = $this->resolveMaxBindParameters($maxBindParameters);

        $identityColumns = $this->resolveColumns($identityProperties, $rows[0], $mapping, 'Identity');
        $updateColumns = $this->resolveBatchUpdateColumns(array_keys($rows[0]), $identityColumns);
        $this->assertBatchUpdateFitsBindLimit($updateColumns, $identityColumns, $effectiveMaxBindParameters);

        $setParts = array_map(
            static fn (string $column): string => $column . ' = :' . QueryBuilder::PARAM_PREFIX_SET . $column,
            $updateColumns,
        );
        $whereParts = array_map(
            static fn (string $column): string => $column . ' = :' . QueryBuilder::PARAM_PREFIX_WHERE . $column,
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
                    $params[QueryBuilder::PARAM_PREFIX_SET . $column] = $row[$column];
                }

                foreach ($identityColumns as $column) {
                    if ($row[$column] === null) {
                        throw new \LogicException(\sprintf(
                            'Cannot batch update: identity column "%s" is null at row index %d.',
                            $column,
                            $index,
                        ));
                    }
                    $params[QueryBuilder::PARAM_PREFIX_WHERE . $column] = $row[$column];
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
     * @param array<string> $propertyNames
     * @param array<string, mixed> $firstRow
     * @return list<string>
     */
    private function resolveColumns(
        array $propertyNames,
        array $firstRow,
        ?Mapping $mapping,
        string $label,
    ): array {
        $columns = [];
        foreach ($propertyNames as $propertyName) {
            $columnName = $this->targetResolver->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $firstRow)) {
                throw new \LogicException(\sprintf('%s property "%s" is not extracted.', $label, $propertyName));
            }
            $columns[] = $columnName;
        }

        return $columns;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function prepareWrite(string|Mapping $target, object $dto, string $operation): array
    {
        [$table, $data] = $this->prepareResolvedWrite($target, $dto, $operation);

        return [$table, $data];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: Mapping|null}
     */
    private function prepareResolvedWrite(
        string|Mapping $target,
        object $dto,
        ?string $operation = null,
        bool $allowEmptyData = false,
    ): array {
        [$table, , $mapping] = $this->targetResolver->resolveTarget($target, $dto);
        $data = $this->extractor->extract($dto, $mapping);

        if (!$allowEmptyData && $data === []) {
            throw new \LogicException(\sprintf('Cannot %s: no data extracted from the DTO.', $operation));
        }

        return [$table, $data, $mapping];
    }

    /**
     * @param array<int|string, string> $identityProperties
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildIdentityWhere(array $identityProperties, array $data, ?Mapping $mapping): array
    {
        return $this->targetResolver->buildWhereFromIdentityProperties($identityProperties, $data, $mapping);
    }

    private function resolveMaxBindParameters(?int $maxBindParameters, ?int $default = null): int
    {
        $effectiveMaxBindParameters = $maxBindParameters
            ?? $default
            ?? DialectFactory::fromDriverName($this->connection->getDriverName())->getMaxBindParameters();

        if ($effectiveMaxBindParameters < 1) {
            throw new \LogicException('maxBindParameters must be greater than zero.');
        }

        return $effectiveMaxBindParameters;
    }

    /**
     * @param list<string> $allColumns
     * @param list<string> $keyColumns
     * @return list<string>
     */
    private function resolveNonKeyColumns(array $allColumns, array $keyColumns): array
    {
        return array_values(array_filter(
            $allColumns,
            static fn (string $column): bool => !\in_array($column, $keyColumns, true),
        ));
    }

    /**
     * @param list<string> $allColumns
     * @param list<string> $identityColumns
     * @return list<string>
     */
    private function resolveBatchUpdateColumns(array $allColumns, array $identityColumns): array
    {
        $updateColumns = $this->resolveNonKeyColumns($allColumns, $identityColumns);

        if ($updateColumns === []) {
            throw new \LogicException('Cannot batch update: no columns left to update after excluding identity properties.');
        }

        return $updateColumns;
    }

    /**
     * @param list<string> $updateColumns
     * @param list<string> $identityColumns
     */
    private function assertBatchUpdateFitsBindLimit(array $updateColumns, array $identityColumns, int $maxBindParameters): void
    {
        $requiredParameters = \count($updateColumns) + \count($identityColumns);
        if ($requiredParameters > $maxBindParameters) {
            throw new \LogicException(\sprintf(
                'Cannot batch update: statement requires %d parameters, but maxBindParameters is %d.',
                $requiredParameters,
                $maxBindParameters,
            ));
        }
    }

    /**
     * @param list<object> $dtos
     * @return array{0: string, 1: list<array<string, mixed>>, 2: Mapping|null}
     */
    private function extractAll(
        string|Mapping $target,
        array $dtos,
        string $operation,
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
