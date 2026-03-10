<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder;

use AsceticSoft\Rowcast\ConnectionInterface;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\DeleteCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\InsertCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\SelectCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpsertCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpdateCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectFactory;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

/**
 * Doctrine DBAL-like query builder.
 *
 * Provides a fluent API for constructing SQL queries (SELECT, INSERT, UPDATE, DELETE).
 */
class QueryBuilder
{
    private ?QueryType $type = null;

    /** @var list<string> */
    private array $select = [];

    /** @var array{0: string, 1: string}|null [table, alias] */
    private ?array $from = null;

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> [type, joinTable, joinAlias, condition] */
    private array $join = [];

    /** @var list<string> */
    private array $where = [];

    /** @var list<string> */
    private array $groupBy = [];

    /** @var list<string> */
    private array $having = [];

    /** @var list<string> */
    private array $orderBy = [];

    private ?int $firstResult = null;
    private ?int $maxResults = null;

    private ?string $insertTable = null;

    /** @var array<string, string> column => placeholder */
    private array $insertValues = [];

    /** @var list<string> */
    private array $upsertConflictColumns = [];

    /** @var list<string> */
    private array $upsertUpdateColumns = [];

    private ?string $updateTable = null;

    /** @var array<string, string> column => placeholder */
    private array $updateSet = [];

    private ?string $deleteTable = null;

    /** @var array<string|int, mixed> */
    private array $parameters = [];

    private ?DialectInterface $dialect = null;
    private readonly TypeConverterInterface $typeConverter;

    public function __construct(
        private readonly ConnectionInterface $connection,
        ?TypeConverterInterface $typeConverter = null,
    ) {
        $this->typeConverter = $typeConverter ?? TypeConverterRegistry::defaults();
    }

    /**
     * Returns the Connection this QueryBuilder is bound to.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    // --- SELECT ---

    /**
     * @param string ...$columns
     */
    public function select(string ...$columns): self
    {
        $this->type = QueryType::Select;
        $this->select = array_values($columns);

        return $this;
    }

    /**
     * @param string ...$columns
     */
    public function addSelect(string ...$columns): self
    {
        $this->type = QueryType::Select;
        $this->select = array_values(array_merge($this->select, $columns));

        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->type = QueryType::Select;
        $this->from = [$table, $alias ?? $table];

        return $this;
    }

    public function join(string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('INNER', $joinTable, $joinAlias, $condition);
    }

    public function innerJoin(string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('INNER', $joinTable, $joinAlias, $condition);
    }

    public function leftJoin(string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('LEFT', $joinTable, $joinAlias, $condition);
    }

    public function rightJoin(string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('RIGHT', $joinTable, $joinAlias, $condition);
    }

    private function addJoin(string $type, string $joinTable, string $joinAlias, string $condition): self
    {
        $this->join[] = [$type, $joinTable, $joinAlias, $condition];

        return $this;
    }

    /**
     * @param string|array<string, mixed> $predicate
     */
    public function where(string|array $predicate): self
    {
        $this->where = [];
        $this->addWherePredicate($predicate);

        return $this;
    }

    /**
     * @param string|array<string, mixed> $predicate
     */
    public function andWhere(string|array $predicate): self
    {
        $this->addWherePredicate($predicate);

        return $this;
    }

    /**
     * @param string|array<string, mixed> $predicate
     */
    public function orWhere(string|array $predicate): self
    {
        $compiled = $this->compileWherePredicate($predicate);
        if ($compiled === null) {
            return $this;
        }

        if ($this->where === []) {
            $this->where[] = $compiled;
        } else {
            $last = array_pop($this->where);
            $this->where[] = '(' . $last . ' OR ' . $compiled . ')';
        }

        return $this;
    }

    /**
     * @param array<string, mixed> ...$groups
     */
    public function whereOr(array ...$groups): self
    {
        $this->where = [];
        $compiled = $this->compileOrGroup($groups);
        if ($compiled !== null) {
            $this->where[] = $compiled;
        }

        return $this;
    }

    /**
     * @param array<string, mixed> ...$groups
     */
    public function andWhereOr(array ...$groups): self
    {
        $compiled = $this->compileOrGroup($groups);
        if ($compiled !== null) {
            $this->where[] = $compiled;
        }

        return $this;
    }

    /**
     * @param string|list<string> $groupBy
     */
    public function groupBy(string|array $groupBy): self
    {
        $this->groupBy = \is_array($groupBy) ? $groupBy : [$groupBy];

        return $this;
    }

    public function having(string $predicate): self
    {
        $this->having = [$predicate];

        return $this;
    }

    public function andHaving(string $predicate): self
    {
        $this->having[] = $predicate;

        return $this;
    }

    public function orderBy(string $sort, string $order = 'ASC'): self
    {
        $this->orderBy = [$sort . ' ' . strtoupper($order)];

        return $this;
    }

    public function addOrderBy(string $sort, string $order = 'ASC'): self
    {
        $this->orderBy[] = $sort . ' ' . strtoupper($order);

        return $this;
    }

    public function setOffset(int $offset): self
    {
        $this->firstResult = $offset;

        return $this;
    }

    public function setLimit(int $limit): self
    {
        $this->maxResults = $limit;

        return $this;
    }

    // --- INSERT ---

    public function insert(string $table): self
    {
        $this->type = QueryType::Insert;
        $this->resetInsertState($table);

        return $this;
    }

    public function upsert(string $table): self
    {
        $this->type = QueryType::Upsert;
        $this->resetInsertState($table);

        return $this;
    }

    /**
     * @param array<string, mixed> $values column => placeholder or direct value
     */
    public function values(array $values): self
    {
        if ($this->type === QueryType::Insert || $this->type === QueryType::Upsert) {
            $this->insertValues = [];

            foreach ($values as $column => $value) {
                $this->setValue($column, $value);
            }

            return $this;
        }

        if ($this->type === QueryType::Update) {
            $this->updateSet = [];

            foreach ($values as $column => $value) {
                $this->set($column, $value);
            }

            return $this;
        }

        throw new \LogicException('values() is available only for insert(), upsert(), or update() queries.');
    }

    public function setValue(string $column, mixed $value): self
    {
        if (\is_string($value) && str_starts_with($value, ':')) {
            $this->insertValues[$column] = $value;

            return $this;
        }

        $this->insertValues[$column] = ':' . $column;
        $this->parameters[$column] = $value;

        return $this;
    }

    public function onConflict(string ...$columns): self
    {
        $this->upsertConflictColumns = array_values($columns);

        return $this;
    }

    /**
     * @param list<string> $columns
     */
    public function doUpdateSet(array $columns): self
    {
        $this->upsertUpdateColumns = $columns;

        return $this;
    }

    // --- UPDATE ---

    public function update(string $table, ?string $alias = null): self
    {
        $this->type = QueryType::Update;
        $this->updateTable = $table . ($alias !== null ? ' ' . $alias : '');
        $this->updateSet = [];

        return $this;
    }

    public function set(string $column, mixed $value): self
    {
        if (\is_string($value) && str_starts_with($value, ':')) {
            $this->updateSet[$column] = $value;

            return $this;
        }

        $paramName = 'v_' . $column;
        $this->updateSet[$column] = ':' . $paramName;
        $this->parameters[$paramName] = $value;

        return $this;
    }

    // --- DELETE ---

    public function delete(string $table, ?string $alias = null): self
    {
        $this->type = QueryType::Delete;
        $this->deleteTable = $table . ($alias !== null ? ' ' . $alias : '');

        return $this;
    }

    // --- Parameters ---

    public function setParameter(string|int $key, mixed $value): self
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function setParameters(array $params): self
    {
        $this->parameters = $params;

        return $this;
    }

    // --- Execution ---

    /**
     * Gets the SQL for the current query.
     */
    public function getSQL(): string
    {
        return match ($this->type) {
            QueryType::Select => new SelectCompiler(
                $this->select,
                $this->from,
                $this->join,
                $this->where,
                $this->groupBy,
                $this->having,
                $this->orderBy,
                $this->maxResults,
                $this->firstResult,
                $this->getDialect(),
            )->compile(),
            QueryType::Insert => new InsertCompiler(
                $this->insertTable,
                $this->insertValues,
            )->compile(),
            QueryType::Upsert => new UpsertCompiler(
                $this->insertTable,
                $this->insertValues,
                $this->upsertConflictColumns,
                $this->upsertUpdateColumns,
                $this->getDialect(),
            )->compile(),
            QueryType::Update => new UpdateCompiler(
                $this->updateTable,
                $this->updateSet,
                $this->where,
            )->compile(),
            QueryType::Delete => new DeleteCompiler(
                $this->deleteTable,
                $this->where,
            )->compile(),
            null => throw new \LogicException('No query type (select/insert/update/delete) has been specified.'),
        };
    }

    private function resetInsertState(string $table): void
    {
        $this->insertTable = $table;
        $this->insertValues = [];
        $this->upsertConflictColumns = [];
        $this->upsertUpdateColumns = [];
    }

    private function getDialect(): DialectInterface
    {
        $this->dialect ??= DialectFactory::fromDriverName($this->connection->getDriverName());

        return $this->dialect;
    }

    /**
     * @param string|array<string, mixed> $predicate
     */
    private function addWherePredicate(string|array $predicate): void
    {
        $compiled = $this->compileWherePredicate($predicate);
        if ($compiled !== null) {
            $this->where[] = $compiled;
        }
    }

    /**
     * @param string|array<int|string, mixed> $predicate
     */
    private function compileWherePredicate(string|array $predicate): ?string
    {
        if (\is_string($predicate)) {
            return $predicate;
        }

        if ($predicate === []) {
            return null;
        }

        $parts = [];
        foreach ($predicate as $key => $value) {
            $stringKey = (string) $key;
            if ($stringKey === '$or') {
                if (!\is_array($value)) {
                    throw new \LogicException('WHERE "$or" expects array of groups.');
                }

                $compiled = $this->compileOrGroup($value);
                if ($compiled !== null) {
                    $parts[] = $compiled;
                }
                continue;
            }

            if ($stringKey === '$and') {
                if (!\is_array($value)) {
                    throw new \LogicException('WHERE "$and" expects array of groups.');
                }

                $compiled = $this->compileAndGroup($value);
                if ($compiled !== null) {
                    $parts[] = $compiled;
                }
                continue;
            }

            $parts[] = $this->compileWhereEntry($stringKey, $value);
        }

        return implode(' AND ', $parts);
    }

    /**
     * @param array<int|string, mixed> $groups
     */
    private function compileOrGroup(array $groups): ?string
    {
        $compiledGroups = [];
        foreach ($groups as $group) {
            if (!\is_array($group)) {
                throw new \LogicException('WHERE "$or" group must be an array.');
            }

            $compiled = $this->compileWherePredicate($group);
            if ($compiled !== null && $compiled !== '') {
                $compiledGroups[] = $compiled;
            }
        }

        if ($compiledGroups === []) {
            return null;
        }

        if (\count($compiledGroups) === 1) {
            return $compiledGroups[0];
        }

        $wrapped = array_map([$this, 'wrapGroup'], $compiledGroups);

        return '(' . implode(' OR ', $wrapped) . ')';
    }

    /**
     * @param array<int|string, mixed> $groups
     */
    private function compileAndGroup(array $groups): ?string
    {
        $compiledGroups = [];
        foreach ($groups as $group) {
            if (!\is_array($group)) {
                throw new \LogicException('WHERE "$and" group must be an array.');
            }

            $compiled = $this->compileWherePredicate($group);
            if ($compiled !== null && $compiled !== '') {
                $compiledGroups[] = $compiled;
            }
        }

        if ($compiledGroups === []) {
            return null;
        }

        if (\count($compiledGroups) === 1) {
            return $compiledGroups[0];
        }

        $wrapped = array_map([$this, 'wrapGroup'], $compiledGroups);

        return '(' . implode(' AND ', $wrapped) . ')';
    }

    private function wrapGroup(string $group): string
    {
        if (str_starts_with($group, '(') && str_ends_with($group, ')')) {
            return $group;
        }

        return '(' . $group . ')';
    }

    private function compileWhereEntry(string $key, mixed $value): string
    {
        $parts = preg_split('/\s+/', trim($key)) ?: [];
        if ($parts === []) {
            throw new \LogicException('WHERE key cannot be empty.');
        }

        $field = (string) array_shift($parts);
        if ($field === '') {
            throw new \LogicException('WHERE key must contain a field name.');
        }

        $operator = strtoupper(implode(' ', $parts));
        if ($operator === '') {
            if ($value === null) {
                return $field . ' IS NULL';
            }

            if (\is_array($value)) {
                return $this->compileInClause($field, $value, 'IN');
            }

            $parameter = $this->nextWhereParameterName($field);
            $this->parameters[$parameter] = $this->normalizeValue($value);

            return $field . ' = :' . $parameter;
        }

        if ($operator === '!=' || $operator === '<>') {
            if ($value === null) {
                return $field . ' IS NOT NULL';
            }

            if (\is_array($value)) {
                return $this->compileInClause($field, $value, 'NOT IN');
            }

            $parameter = $this->nextWhereParameterName($field);
            $this->parameters[$parameter] = $this->normalizeValue($value);

            return $field . ' ' . $operator . ' :' . $parameter;
        }

        if ($operator === 'IN' || $operator === 'NOT IN') {
            if (!\is_array($value)) {
                throw new \LogicException(\sprintf('WHERE "%s %s" expects array value.', $field, $operator));
            }

            return $this->compileInClause($field, $value, $operator);
        }

        if ($operator === 'BETWEEN') {
            if (!\is_array($value) || \count($value) !== 2) {
                throw new \LogicException(\sprintf('WHERE "%s BETWEEN" expects [from, to].', $field));
            }

            $bounds = array_values($value);
            $fromParam = $this->nextWhereParameterName($field);
            $this->parameters[$fromParam] = $this->normalizeValue($bounds[0]);
            $toParam = $this->nextWhereParameterName($field);
            $this->parameters[$toParam] = $this->normalizeValue($bounds[1]);

            return $field . ' BETWEEN :' . $fromParam . ' AND :' . $toParam;
        }

        $supportedOperators = $this->getDialect()->getSupportedOperators();
        if (isset($supportedOperators[$operator])) {
            $parameter = $this->nextWhereParameterName($field);
            $this->parameters[$parameter] = $this->normalizeValue($value);

            return $field . ' ' . $operator . ' :' . $parameter;
        }

        throw new \LogicException(\sprintf('Unsupported WHERE operator "%s" for field "%s".', $operator, $field));
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function compileInClause(string $field, array $values, string $keyword): string
    {
        if ($values === []) {
            return $keyword === 'NOT IN' ? '1 = 1' : '1 = 0';
        }

        $placeholders = [];
        foreach ($values as $value) {
            $parameter = $this->nextWhereParameterName($field);
            $this->parameters[$parameter] = $this->normalizeValue($value);
            $placeholders[] = ':' . $parameter;
        }

        return $field . ' ' . $keyword . ' (' . implode(', ', $placeholders) . ')';
    }

    private function normalizeValue(mixed $value): mixed
    {
        return $this->typeConverter->toDb($value);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function normalizedParameters(): array
    {
        return array_map($this->normalizeValue(...), $this->parameters);
    }

    private function nextWhereParameterName(string $field): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '_', $field);
        $base = 'w_' . ($sanitized !== '' ? $sanitized : 'p');
        $name = $base;
        $i = 1;

        while (\array_key_exists($name, $this->parameters)) {
            $name = $base . '_' . $i;
            ++$i;
        }

        return $name;
    }

    /**
     * Executes the query and returns the PDOStatement.
     */
    public function executeQuery(): \PDOStatement
    {
        return $this->connection->executeQuery($this->getSQL(), $this->normalizedParameters());
    }

    /**
     * Executes the statement and returns the number of affected rows.
     */
    public function executeStatement(): int
    {
        return $this->connection->executeStatement($this->getSQL(), $this->normalizedParameters());
    }

    /**
     * Executes the query and returns all rows as associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(): array
    {
        return $this->connection->fetchAllAssociative($this->getSQL(), $this->normalizedParameters());
    }

    /**
     * Executes the query and returns the first row as an associative array, or false.
     *
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(): array|false
    {
        return $this->connection->fetchAssociative($this->getSQL(), $this->normalizedParameters());
    }

    /**
     * Executes the query and returns the first column of the first row, or false.
     */
    public function fetchOne(): mixed
    {
        return $this->connection->fetchOne($this->getSQL(), $this->normalizedParameters());
    }

    /**
     * Executes the query and returns an iterable that yields rows one at a time.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function toIterable(): iterable
    {
        return $this->connection->toIterable($this->getSQL(), $this->normalizedParameters());
    }
}
