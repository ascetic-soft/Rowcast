<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;

final readonly class WriteOperations
{
    public function __construct(
        private ConnectionInterface $connection,
        private Extractor $extractor,
        private TargetResolver $targetResolver,
    ) {
    }

    public function insert(string|Mapping $target, object $dto): void
    {
        [$table, $data] = $this->prepareWrite($target, $dto, 'insert');

        $values = $this->createPlaceholders($data);
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
     * @param array<string, mixed> $where
     */
    public function update(string|Mapping $target, object $dto, array $where): int
    {
        [$table, $data] = $this->prepareWrite($target, $dto, 'update');
        $this->assertWhereIsNotEmpty($where, 'update');

        $qb = $this->connection->createQueryBuilder();
        $qb->update($table);

        foreach ($data as $column => $value) {
            $paramName = QueryBuilder::PARAM_PREFIX_SET . $column;
            $qb->set($column, ':' . $paramName);
            $qb->setParameter($paramName, $value);
        }

        $qb->where($where);

        return $qb->executeStatement();
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string|Mapping $target, array $where): int
    {
        [$table] = $this->targetResolver->resolveTarget($target);
        $this->assertWhereIsNotEmpty($where, 'delete');

        $qb = $this->connection->createQueryBuilder()->delete($table);
        $qb->where($where);

        return $qb->executeStatement();
    }

    public function save(string|Mapping $target, object $dto, string ...$identityProperties): void
    {
        $this->assertPropertyNamesAreNotEmpty($identityProperties, 'save', 'identity');

        [$table, $data, $mapping] = $this->prepareResolvedWrite($target, $dto, allowEmptyData: true);
        $where = $this->buildIdentityWhere($identityProperties, $data, $mapping);

        // save() is a convenience flow that works without requiring a database
        // conflict constraint. It intentionally checks for existence first and
        // then chooses insert or update. Prefer upsert() when the database
        // supports native conflict handling and write-path round-trips matter.
        $qb = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($table)
            ->setLimit(1)
        ;
        $qb->where($where);

        if ($qb->fetchOne() === false) {
            $this->insert($target, $dto);

            return;
        }

        $this->update($target, $dto, $where);
    }

    public function upsert(string|Mapping $target, object $dto, string ...$conflictProperties): int
    {
        $this->assertPropertyNamesAreNotEmpty($conflictProperties, 'upsert', 'conflict');

        [$table, $data, $mapping] = $this->prepareResolvedWrite($target, $dto, 'upsert');
        $conflictProperties = array_values($conflictProperties);

        $conflictColumns = $this->targetResolver->resolveExtractedColumns($conflictProperties, $data, $mapping, 'Conflict');

        $updateColumns = array_values(array_filter(
            array_keys($data),
            static fn (string $column): bool => !\in_array($column, $conflictColumns, true),
        ));

        $qb = $this->connection->createQueryBuilder()
            ->upsert($table)
            ->values($this->createPlaceholders($data))
            ->onConflict(...$conflictColumns)
            ->doUpdateSet($updateColumns)
        ;
        foreach ($data as $column => $value) {
            $qb->setParameter($column, $value);
        }

        return $qb->executeStatement();
    }

    /**
     * @param array<string, mixed> $where
     */
    private function assertWhereIsNotEmpty(array $where, string $operation): void
    {
        if ($where === []) {
            throw new \LogicException(\sprintf('Cannot %s: WHERE conditions are required.', $operation));
        }
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function createPlaceholders(array $data): array
    {
        $values = [];
        foreach (array_keys($data) as $column) {
            $values[$column] = ':' . $column;
        }

        return $values;
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

        $this->assertExtractedDataIsNotEmpty($data, $operation, $allowEmptyData);

        return [$table, $data, $mapping];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertExtractedDataIsNotEmpty(array $data, ?string $operation, bool $allowEmptyData): void
    {
        if (!$allowEmptyData && $data === []) {
            throw new \LogicException(\sprintf('Cannot %s: no data extracted from the DTO.', $operation));
        }
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
}
