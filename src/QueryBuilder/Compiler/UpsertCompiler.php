<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

final readonly class UpsertCompiler implements SqlCompilerInterface
{
    /**
     * @param array<string, string> $values column => placeholder
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    public function __construct(
        private ?string $table,
        private array $values,
        private array $conflictColumns,
        private array $updateColumns,
        private string $driverName,
    ) {
    }

    public function compile(): string
    {
        if ($this->table === null || $this->values === []) {
            throw new \LogicException('UPSERT requires table and values.');
        }

        $columns = array_keys($this->values);
        $placeholders = array_values($this->values);
        $sql = 'INSERT INTO ' . $this->table
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        if ($this->driverName === 'mysql') {
            if ($this->updateColumns === []) {
                return $sql;
            }

            $parts = array_map(
                static fn (string $column): string => $column . ' = VALUES(' . $column . ')',
                $this->updateColumns,
            );

            return $sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $parts);
        }

        if ($this->driverName === 'pgsql' || $this->driverName === 'sqlite') {
            if ($this->conflictColumns === []) {
                throw new \LogicException('UPSERT requires conflict columns for pgsql/sqlite.');
            }

            if ($this->updateColumns === []) {
                return $sql . ' ON CONFLICT (' . implode(', ', $this->conflictColumns) . ') DO NOTHING';
            }

            $parts = array_map(
                static fn (string $column): string => $column . ' = EXCLUDED.' . $column,
                $this->updateColumns,
            );

            return $sql
                . ' ON CONFLICT (' . implode(', ', $this->conflictColumns) . ')'
                . ' DO UPDATE SET ' . implode(', ', $parts);
        }

        throw new \LogicException(\sprintf('UPSERT is not supported for driver "%s".', $this->driverName));
    }
}
