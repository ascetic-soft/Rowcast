<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\Hydration\HydratorInterface;
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;
use AsceticSoft\Rowcast\Mapping\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;
use AsceticSoft\Rowcast\Persistence\DtoExtractor;
use AsceticSoft\Rowcast\Persistence\ValueConverterInterface;
use AsceticSoft\Rowcast\Persistence\ValueConverterRegistry;

/**
 * Lightweight DataMapper for persisting and querying DTO objects.
 *
 * Supports two modes:
 * - **Auto mode**: pass a table name (for write) or class-string (for read).
 *   Property ↔ column mapping is done via NameConverter.
 * - **Explicit mode**: pass a ResultSetMapping for full control over column ↔ property mapping
 *   and table name.
 */
final readonly class DataMapper
{
    private HydratorInterface $hydrator;
    private DtoExtractor $dtoExtractor;
    private ValueConverterInterface $valueConverter;

    public function __construct(
        private ConnectionInterface      $connection,
        ?NameConverterInterface          $nameConverter = null,
        ?HydratorInterface               $hydrator = null,
        ?DtoExtractor                    $dtoExtractor = null,
        ?ValueConverterInterface         $valueConverter = null,
    ) {
        $nameConverter ??= new SnakeCaseToCamelCaseConverter();
        $this->valueConverter = $valueConverter ?? ValueConverterRegistry::createDefault();
        $this->hydrator = $hydrator ?? new ReflectionHydrator(nameConverter: $nameConverter);
        $this->dtoExtractor = $dtoExtractor ?? new DtoExtractor($nameConverter, $this->valueConverter);
    }

    /**
     * Inserts a DTO into the database.
     *
     * Extracts property values from the DTO, converts property names to column names
     * (via RSM or NameConverter), and builds an INSERT statement.
     * Uninitialized properties are skipped (useful for auto-increment IDs).
     *
     * @param string|ResultSetMapping $target Table name (string) or ResultSetMapping (provides table + mapping)
     * @param object                  $dto    The DTO object to insert
     *
     * @return string|false Last insert ID, or false on failure
     */
    public function insert(string|ResultSetMapping $target, object $dto): string|false
    {
        [$table, $rsm] = $this->resolveWriteTarget($target);
        $data = $this->dtoExtractor->extract($dto, $rsm);

        if ($data === []) {
            throw new \LogicException('Cannot insert: no data extracted from the DTO.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->insert($table);

        $values = [];
        foreach ($data as $column => $value) {
            $values[$column] = ':' . $column;
            $qb->setParameter($column, $value);
        }

        $qb->values($values);
        $qb->executeStatement();

        return $this->connection->lastInsertId();
    }

    /**
     * Updates rows in the database using values from a DTO.
     *
     * Extracts property values from the DTO for the SET clause.
     * The WHERE clause is built from the $where array (column => value, joined with AND).
     *
     * @param string|ResultSetMapping  $target Table name (string) or ResultSetMapping
     * @param object                   $dto    The DTO object with new values
     * @param array<string, mixed>     $where  WHERE conditions as column => value pairs
     *
     * @return int Number of affected rows
     */
    public function update(string|ResultSetMapping $target, object $dto, array $where): int
    {
        [$table, $rsm] = $this->resolveWriteTarget($target);
        $data = $this->dtoExtractor->extract($dto, $rsm);

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
     * Deletes rows from the database.
     *
     * @param string|ResultSetMapping  $target Table name (string) or ResultSetMapping
     * @param array<string, mixed>     $where  WHERE conditions as column => value pairs
     *
     * @return int Number of affected rows
     */
    public function delete(string|ResultSetMapping $target, array $where): int
    {
        [$table] = $this->resolveWriteTarget($target);

        if ($where === []) {
            throw new \LogicException('Cannot delete: WHERE conditions are required.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->delete($table);

        $this->applyWhere($qb, $where);

        return $qb->executeStatement();
    }

    /**
     * Finds all matching rows and hydrates them into DTO objects.
     *
     * @template T of object
     *
     * @param class-string<T>|ResultSetMapping $target  DTO class name or ResultSetMapping
     * @param array<string, mixed>             $where   WHERE conditions as column => value pairs
     * @param array<string, string>            $orderBy ORDER BY as column => direction ('ASC'|'DESC')
     * @param int|null                         $limit   Maximum number of rows to return
     * @param int|null                         $offset  Number of rows to skip
     *
     * @return list<T>
     */
    public function findAll(
        string|ResultSetMapping $target,
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        [$qb, $className, $rsm] = $this->buildSelectQuery($target, $where, $orderBy, $limit, $offset);

        $rows = $qb->fetchAllAssociative();

        /** @var list<T> */
        return $this->hydrator->hydrateAll($className, $rows, $rsm);
    }

    /**
     * Finds all matching rows and yields them as hydrated DTO objects one at a time.
     *
     * Uses PDO cursor-based fetching for memory-efficient iteration over large result sets.
     * Each row is hydrated lazily — only when consumed from the iterable.
     *
     * @template T of object
     *
     * @param class-string<T>|ResultSetMapping $target  DTO class name or ResultSetMapping
     * @param array<string, mixed>             $where   WHERE conditions as column => value pairs
     * @param array<string, string>            $orderBy ORDER BY as column => direction ('ASC'|'DESC')
     * @param int|null                         $limit   Maximum number of rows to return
     * @param int|null                         $offset  Number of rows to skip
     *
     * @return iterable<int, T>
     */
    public function iterateAll(
        string|ResultSetMapping $target,
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): iterable {
        [$qb, $className, $rsm] = $this->buildSelectQuery($target, $where, $orderBy, $limit, $offset);

        foreach ($qb->toIterable() as $row) {
            /** @var T $object */
            $object = $this->hydrator->hydrate($className, $row, $rsm);

            yield $object;
        }
    }

    /**
     * Finds a single row and hydrates it into a DTO object.
     *
     * @template T of object
     *
     * @param class-string<T>|ResultSetMapping $target DTO class name or ResultSetMapping
     * @param array<string, mixed>             $where  WHERE conditions as column => value pairs
     *
     * @return T|null The hydrated DTO, or null if no row found
     */
    public function findOne(
        string|ResultSetMapping $target,
        array $where = [],
    ): object|null {
        [$qb, $className, $rsm] = $this->buildSelectQuery($target, $where, limit: 1);

        $row = $qb->fetchAssociative();

        if ($row === false) {
            return null;
        }

        /** @var T $result */
        $result = $this->hydrator->hydrate($className, $row, $rsm);

        return $result;
    }

    /**
     * Returns the underlying Connection instance.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Builds a SELECT QueryBuilder from read-target parameters.
     *
     * @param class-string|ResultSetMapping $target
     * @param array<string, mixed>          $where
     * @param array<string, string>         $orderBy
     *
     * @return array{0: QueryBuilder\QueryBuilder, 1: class-string, 2: ResultSetMapping|null}
     */
    private function buildSelectQuery(
        string|ResultSetMapping $target,
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        [$table, $className, $rsm] = $this->resolveReadTarget($target);

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from($table);

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

        return [$qb, $className, $rsm];
    }

    /**
     * Resolves the table name and optional RSM for write operations (insert/update/delete).
     *
     * @return array{0: string, 1: ResultSetMapping|null}
     */
    private function resolveWriteTarget(string|ResultSetMapping $target): array
    {
        if ($target instanceof ResultSetMapping) {
            $table = $target->getTable();

            if ($table === null) {
                throw new \LogicException(
                    'ResultSetMapping must have a table name for write operations. '
                    . 'Pass the table name in the ResultSetMapping constructor.',
                );
            }

            return [$table, $target];
        }

        return [$target, null];
    }

    /**
     * Resolves table name, class name, and optional RSM for read operations (findAll/findOne).
     *
     * @param class-string|ResultSetMapping $target
     *
     * @return array{0: string, 1: class-string, 2: ResultSetMapping|null}
     */
    private function resolveReadTarget(string|ResultSetMapping $target): array
    {
        if ($target instanceof ResultSetMapping) {
            $table = $target->getTable();

            if ($table === null) {
                throw new \LogicException(
                    'ResultSetMapping must have a table name for read operations. '
                    . 'Pass the table name in the ResultSetMapping constructor.',
                );
            }

            return [$table, $target->getClassName(), $target];
        }

        return [$this->deriveTableName($target), $target, null];
    }

    /**
     * Derives a table name from a fully-qualified class name.
     *
     * Convention: short class name -> snake_case -> append 's'.
     * Examples: User -> users, UserProfile -> user_profiles
     *
     * @param class-string $className
     */
    private function deriveTableName(string $className): string
    {
        $shortName = new \ReflectionClass($className)->getShortName();

        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return strtolower($replaced ?? $shortName) . 's';
    }

    /**
     * Applies WHERE conditions to a QueryBuilder from a column => value array.
     *
     * @param array<string, mixed> $where       Column => value pairs
     * @param string               $paramPrefix Prefix for parameter names to avoid collisions
     */
    private function applyWhere(QueryBuilder\QueryBuilder $qb, array $where, string $paramPrefix = ''): void
    {
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

            $qb->setParameter($paramName, $this->valueConverter->convertForDb($value));
        }
    }
}
