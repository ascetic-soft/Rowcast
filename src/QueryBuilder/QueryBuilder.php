<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder;

use AsceticSoft\Rowcast\ConnectionInterface;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\DeleteCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\InsertCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\SelectCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpdateCompiler;

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

    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> [type, fromAlias, joinTable, joinAlias, condition] */
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

    private ?string $updateTable = null;

    /** @var array<string, string> column => placeholder */
    private array $updateSet = [];

    private ?string $deleteTable = null;

    /** @var array<string|int, mixed> */
    private array $parameters = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
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

    public function join(string $fromAlias, string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('INNER', $fromAlias, $joinTable, $joinAlias, $condition);
    }

    public function innerJoin(string $fromAlias, string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('INNER', $fromAlias, $joinTable, $joinAlias, $condition);
    }

    public function leftJoin(string $fromAlias, string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('LEFT', $fromAlias, $joinTable, $joinAlias, $condition);
    }

    public function rightJoin(string $fromAlias, string $joinTable, string $joinAlias, string $condition): self
    {
        return $this->addJoin('RIGHT', $fromAlias, $joinTable, $joinAlias, $condition);
    }

    private function addJoin(string $type, string $fromAlias, string $joinTable, string $joinAlias, string $condition): self
    {
        $this->join[] = [$type, $fromAlias, $joinTable, $joinAlias, $condition];

        return $this;
    }

    public function where(string $predicate): self
    {
        $this->where = [$predicate];

        return $this;
    }

    public function andWhere(string $predicate): self
    {
        $this->where[] = $predicate;

        return $this;
    }

    public function orWhere(string $predicate): self
    {
        if ($this->where === []) {
            $this->where[] = $predicate;
        } else {
            $last = array_pop($this->where);
            $this->where[] = '(' . $last . ' OR ' . $predicate . ')';
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

    /**
     * Sets offset
     */
    public function setFirstResult(int $firstResult): self
    {
        $this->firstResult = $firstResult;

        return $this;
    }

    /**
     * Sets limit
     */
    public function setMaxResults(int $maxResults): self
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    // --- INSERT ---

    public function insert(string $table): self
    {
        $this->type = QueryType::Insert;
        $this->insertTable = $table;
        $this->insertValues = [];

        return $this;
    }

    /**
     * @param array<string, string> $values column => placeholder
     */
    public function values(array $values): self
    {
        $this->insertValues = $values;

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

    public function set(string $column, string $value): self
    {
        $this->updateSet[$column] = $value;

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
                $this->connection->getDriverName(),
            )->compile(),
            QueryType::Insert => new InsertCompiler(
                $this->insertTable,
                $this->insertValues,
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

    /**
     * Executes the query and returns the PDOStatement.
     */
    public function executeQuery(): \PDOStatement
    {
        return $this->connection->executeQuery($this->getSQL(), $this->parameters);
    }

    /**
     * Executes the statement and returns the number of affected rows.
     */
    public function executeStatement(): int
    {
        return $this->connection->executeStatement($this->getSQL(), $this->parameters);
    }

    /**
     * Executes the query and returns all rows as associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(): array
    {
        return $this->connection->fetchAllAssociative($this->getSQL(), $this->parameters);
    }

    /**
     * Executes the query and returns the first row as an associative array, or false.
     *
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(): array|false
    {
        return $this->connection->fetchAssociative($this->getSQL(), $this->parameters);
    }

    /**
     * Executes the query and returns the first column of the first row, or false.
     */
    public function fetchOne(): mixed
    {
        return $this->connection->fetchOne($this->getSQL(), $this->parameters);
    }

    /**
     * Executes the query and returns an iterable that yields rows one at a time.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function toIterable(): iterable
    {
        return $this->connection->toIterable($this->getSQL(), $this->parameters);
    }
}
