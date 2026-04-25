<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectFactory;

final readonly class BatchWriteOperations
{
    public function __construct(
        private ConnectionInterface $connection,
        private Extractor $extractor,
        private TargetResolver $targetResolver,
        private BulkWriter $bulkWriter,
    ) {
    }

    /**
     * @param list<object> $dtos
     */
    public function batchInsert(string|Mapping $target, array $dtos, ?int $maxBindParameters = null): void
    {
        if ($this->isEmptyBatch($dtos)) {
            return;
        }

        [$table, $rows] = $this->extractAll($target, $dtos, 'insert');
        $effectiveMaxBindParameters = $this->resolveMaxBindParameters($maxBindParameters);

        $this->bulkWriter->executeChunkedInsert($table, $rows, $effectiveMaxBindParameters);
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
        $this->assertPropertyNamesAreNotEmpty($conflictProperties, 'batch upsert', 'conflict');

        if ($this->isEmptyBatch($dtos)) {
            return;
        }

        [$table, $rows, $mapping] = $this->extractAll($target, $dtos, 'upsert');
        $dialect = DialectFactory::fromDriverName($this->connection->getDriverName());
        if (!$dialect->supportsUpsert()) {
            throw new \LogicException('Cannot batch upsert: UPSERT is not supported by the current database driver.');
        }

        $effectiveMaxBindParameters = $this->resolveMaxBindParameters($maxBindParameters, $dialect->getMaxBindParameters());
        $conflictColumns = $this->targetResolver->resolveExtractedColumns($conflictProperties, $rows[0], $mapping, 'Conflict');
        $updateColumns = $this->resolveNonKeyColumns(array_keys($rows[0]), $conflictColumns);
        $upsertClause = $dialect->compileUpsertClause($conflictColumns, $updateColumns);

        $this->bulkWriter->executeChunkedInsert($table, $rows, $effectiveMaxBindParameters, $upsertClause);
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
        $this->assertPropertyNamesAreNotEmpty($identityProperties, 'batch update', 'identity');

        if ($this->isEmptyBatch($dtos)) {
            return;
        }

        [$table, $rows, $mapping] = $this->extractAll($target, $dtos, 'update');
        $effectiveMaxBindParameters = $this->resolveMaxBindParameters($maxBindParameters);
        $identityColumns = $this->targetResolver->resolveExtractedColumns($identityProperties, $rows[0], $mapping, 'Identity');
        $updateColumns = $this->resolveBatchUpdateColumns(array_keys($rows[0]), $identityColumns);

        $this->bulkWriter->executeBatchUpdate(
            $table,
            $rows,
            $updateColumns,
            $identityColumns,
            $effectiveMaxBindParameters,
        );
    }

    /**
     * @param array<int, object> $dtos
     */
    private function isEmptyBatch(array $dtos): bool
    {
        return $dtos === [];
    }

    /**
     * @param array<int|string, string> $propertyNames
     */
    private function assertPropertyNamesAreNotEmpty(array $propertyNames, string $operation, string $label): void
    {
        if ($propertyNames === []) {
            throw new \LogicException(\sprintf('Cannot %s: %s properties are required.', $operation, $label));
        }
    }

    private function resolveMaxBindParameters(?int $maxBindParameters, ?int $default = null): int
    {
        $effectiveMaxBindParameters = $maxBindParameters
            ?? $default
            ?? DialectFactory::fromDriverName($this->connection->getDriverName())->getMaxBindParameters();

        $this->assertMaxBindParametersIsValid($effectiveMaxBindParameters);

        return $effectiveMaxBindParameters;
    }

    private function assertMaxBindParametersIsValid(int $maxBindParameters): void
    {
        if ($maxBindParameters < 1) {
            throw new \LogicException('maxBindParameters must be greater than zero.');
        }
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
     * @param list<object> $dtos
     * @return array{0: string, 1: list<array<string, mixed>>, 2: Mapping|null}
     */
    private function extractAll(
        string|Mapping $target,
        array $dtos,
        string $operation,
    ): array {
        if ($this->isEmptyBatch($dtos)) {
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
}
