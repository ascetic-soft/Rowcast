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
}
