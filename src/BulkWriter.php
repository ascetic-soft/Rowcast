<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\SqlFragments;
use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

final readonly class BulkWriter
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function executeChunkedInsert(string $table, array $rows, int $maxBindParameters, string $suffix = ''): void
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
        $columnPrefixes = $this->buildColumnPrefixes($columns);

        $this->connection->transactional(function () use ($table, $rows, $columns, $columnPrefixes, $chunkSize, $suffix): void {
            $rowCount = \count($rows);
            $sqlByChunkSize = [];

            for ($offset = 0; $offset < $rowCount; $offset += $chunkSize) {
                $currentChunkSize = min($chunkSize, $rowCount - $offset);
                $sqlByChunkSize[$currentChunkSize] = SqlFragments::buildMultiRowInsertSql($table, $columns, $currentChunkSize) . $suffix;
                $sql = $sqlByChunkSize[$currentChunkSize];
                $params = [];

                for ($rowIndex = 0; $rowIndex < $currentChunkSize; ++$rowIndex) {
                    $row = $rows[$offset + $rowIndex];

                    foreach ($columns as $columnIndex => $column) {
                        $params[$columnPrefixes[$columnIndex] . $rowIndex] = $row[$column];
                    }
                }

                $this->connection->executeStatement($sql, $params);
            }
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $updateColumns
     * @param list<string> $identityColumns
     */
    public function executeBatchUpdate(
        string $table,
        array $rows,
        array $updateColumns,
        array $identityColumns,
        int $maxBindParameters,
    ): void {
        $this->assertBatchUpdateFitsBindLimit($updateColumns, $identityColumns, $maxBindParameters);

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

        $updateParameterMap = $this->buildParameterMap($updateColumns, QueryBuilder::PARAM_PREFIX_SET);
        $identityParameterMap = $this->buildParameterMap($identityColumns, QueryBuilder::PARAM_PREFIX_WHERE);

        $this->connection->transactional(function () use ($rows, $updateParameterMap, $identityParameterMap, $sql): void {
            $statement = $this->connection->getPdo()->prepare($sql);
            foreach ($rows as $index => $row) {
                $params = [];

                foreach ($updateParameterMap as $column => $parameterName) {
                    $params[$parameterName] = $row[$column];
                }

                foreach ($identityParameterMap as $column => $parameterName) {
                    if ($row[$column] === null) {
                        throw new \LogicException(\sprintf(
                            'Cannot batch update: identity column "%s" is null at row index %d.',
                            $column,
                            $index,
                        ));
                    }
                    $params[$parameterName] = $row[$column];
                }

                $statement->execute($params);
            }
        });
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
     * @param list<string> $columns
     * @return array<string, string>
     */
    private function buildParameterMap(array $columns, string $prefix): array
    {
        $parameterMap = [];

        foreach ($columns as $column) {
            $parameterMap[$column] = $prefix . $column;
        }

        return $parameterMap;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function buildColumnPrefixes(array $columns): array
    {
        $prefixes = [];

        foreach ($columns as $column) {
            $prefixes[] = $column . '_';
        }

        return $prefixes;
    }
}
