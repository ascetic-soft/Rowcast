<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

final readonly class DataMapper
{
    private Hydrator $hydrator;
    private Extractor $extractor;
    private TargetResolver $targetResolver;
    private BulkWriter $bulkWriter;
    private ReadOperations $readOperations;
    private WriteOperations $writeOperations;
    private BatchWriteOperations $batchWriteOperations;

    public function __construct(
        private ConnectionInterface $connection,
        ?NameConverterInterface $nameConverter = null,
        ?TypeConverterInterface $typeConverter = null,
        ?Hydrator $hydrator = null,
        ?Extractor $extractor = null,
        ?TargetResolver $targetResolver = null,
    ) {
        $nameConverter ??= new SnakeCaseToCamelCase();
        $typeConverter ??= TypeConverterRegistry::defaults();
        $this->hydrator = $hydrator ?? new Hydrator($typeConverter, $nameConverter);
        $this->extractor = $extractor ?? new Extractor($nameConverter, $typeConverter);
        $this->targetResolver = $targetResolver ?? new TargetResolver($nameConverter);
        $this->bulkWriter = new BulkWriter($this->connection);
        $this->readOperations = new ReadOperations($this->connection, $this->hydrator, $this->targetResolver);
        $this->writeOperations = new WriteOperations($this->connection, $this->extractor, $this->targetResolver);
        $this->batchWriteOperations = new BatchWriteOperations($this->connection, $this->extractor, $this->targetResolver, $this->bulkWriter);
    }

    public function insert(string|Mapping $target, object $dto): void
    {
        $this->writeOperations->insert($target, $dto);
    }

    /**
     * @param list<object> $dtos
     */
    public function batchInsert(string|Mapping $target, array $dtos, ?int $maxBindParameters = null): void
    {
        $this->batchWriteOperations->batchInsert($target, $dtos, $maxBindParameters);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function update(string|Mapping $target, object $dto, array $where): int
    {
        return $this->writeOperations->update($target, $dto, $where);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string|Mapping $target, array $where): int
    {
        return $this->writeOperations->delete($target, $where);
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
        return $this->readOperations->findAll($target, $where, $orderBy, $limit, $offset);
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
        return $this->readOperations->iterateAll($target, $where, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $where
     */
    public function findOne(string|Mapping $target, array $where = []): ?object
    {
        return $this->readOperations->findOne($target, $where);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function hydrate(string|Mapping $target, array $row): object
    {
        return $this->readOperations->hydrate($target, $row);
    }

    /**
     * @param string|Mapping $target
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    public function hydrateAll(string|Mapping $target, array $rows): array
    {
        return $this->readOperations->hydrateAll($target, $rows);
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
        $this->writeOperations->save($target, $dto, ...$identityProperties);
    }

    public function upsert(string|Mapping $target, object $dto, string ...$conflictProperties): int
    {
        return $this->writeOperations->upsert($target, $dto, ...$conflictProperties);
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
        $this->batchWriteOperations->batchUpsert($target, $dtos, $conflictProperties, $maxBindParameters);
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
        $this->batchWriteOperations->batchUpdate($target, $dtos, $identityProperties, $maxBindParameters);
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

}
