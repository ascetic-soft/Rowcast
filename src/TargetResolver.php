<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

final class TargetResolver
{
    /** @var array<class-string, string> */
    private static array $derivedTableNames = [];

    public function __construct(
        private NameConverterInterface $nameConverter,
    ) {
    }

    /**
     * @return array{0: string, 1: class-string, 2: Mapping|null}
     */
    public function resolveTarget(string|Mapping $target, ?object $dto = null): array
    {
        if ($target instanceof Mapping) {
            /** @var class-string $className */
            $className = $target->getClassName();

            return [$target->getTable(), $className, $target];
        }

        if (class_exists($target)) {
            /** @var class-string $target */
            return [$this->deriveTableName($target), $target, null];
        }

        if ($dto === null) {
            throw new \LogicException(\sprintf('Unknown class-string target "%s".', $target));
        }

        $className = $dto::class;

        return [$target, $className, null];
    }

    /**
     * @param array<int|string, string> $identityProperties
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function buildWhereFromIdentityProperties(array $identityProperties, array $data, ?Mapping $mapping): array
    {
        $where = [];

        foreach ($identityProperties as $propertyName) {
            $columnName = $this->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $data)) {
                throw new \LogicException(\sprintf('Identity property "%s" is not extracted.', $propertyName));
            }
            $where[$columnName] = $data[$columnName];
        }

        return $where;
    }

    /**
     * @param list<string> $propertyNames
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public function resolveExtractedColumns(array $propertyNames, array $data, ?Mapping $mapping, string $label): array
    {
        $columns = [];

        foreach ($propertyNames as $propertyName) {
            $columnName = $this->resolveColumnName($propertyName, $mapping);
            if (!\array_key_exists($columnName, $data)) {
                throw new \LogicException(\sprintf('%s property "%s" is not extracted.', $label, $propertyName));
            }
            $columns[] = $columnName;
        }

        return $columns;
    }

    public function resolveColumnName(string $propertyName, ?Mapping $mapping): string
    {
        return $mapping?->getColumnForProperty($propertyName) ?? $this->nameConverter->toColumnName($propertyName);
    }

    /**
     * @param class-string $className
     */
    private function deriveTableName(string $className): string
    {
        if (isset(self::$derivedTableNames[$className])) {
            return self::$derivedTableNames[$className];
        }

        $shortName = new \ReflectionClass($className)->getShortName();
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return self::$derivedTableNames[$className] = strtolower($replaced ?? $shortName) . 's';
    }
}
