<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

final readonly class SelectCompiler implements SqlCompilerInterface
{
    /**
     * @param list<string>                                                           $select
     * @param array{0: string, 1: string}|null                                       $from
     * @param list<array{0: string, 1: string, 2: string, 3: string, 4: string}>    $join
     * @param list<string>                                                           $where
     * @param list<string>                                                           $groupBy
     * @param list<string>                                                           $having
     * @param list<string>                                                           $orderBy
     */
    public function __construct(
        private array   $select,
        private ?array  $from,
        private array   $join,
        private array   $where,
        private array   $groupBy,
        private array   $having,
        private array   $orderBy,
        private ?int    $maxResults,
        private ?int    $firstResult,
        private string  $driverName,
    ) {
    }

    public function compile(): string
    {
        if ($this->from === null) {
            throw new \LogicException('FROM clause is required for SELECT.');
        }

        $parts = [];

        $parts[] = 'SELECT ' . ($this->select !== [] ? implode(', ', $this->select) : '*');
        $parts[] = 'FROM ' . $this->from[0] . ($this->from[1] !== $this->from[0] ? ' ' . $this->from[1] : '');

        foreach ($this->join as [$type, $fromAlias, $joinTable, $joinAlias, $condition]) {
            $parts[] = $type . ' JOIN ' . $joinTable . ' ' . $joinAlias . ' ON ' . $condition;
        }

        if ($this->where !== []) {
            $parts[] = 'WHERE ' . implode(' AND ', $this->where);
        }

        if ($this->groupBy !== []) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having !== []) {
            $parts[] = 'HAVING ' . implode(' AND ', $this->having);
        }

        if ($this->orderBy !== []) {
            $parts[] = 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        $sql = implode(' ', $parts);

        if ($this->maxResults !== null) {
            $limit = $this->maxResults;
            $offset = $this->firstResult ?? 0;

            if ($this->driverName === 'sqlite' || $this->driverName === 'mysql' || $this->driverName === 'pgsql') {
                $sql .= ' LIMIT ' . $limit;
                if ($offset > 0) {
                    $sql .= ' OFFSET ' . $offset;
                }
            }
        }

        return $sql;
    }
}
