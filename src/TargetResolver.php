<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

final readonly class TargetResolver
{
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

    public function resolveColumnName(string $propertyName, ?Mapping $mapping): string
    {
        return $mapping?->getColumnForProperty($propertyName) ?? $this->nameConverter->toColumnName($propertyName);
    }

    /**
     * @param class-string $className
     */
    private function deriveTableName(string $className): string
    {
        $shortName = new \ReflectionClass($className)->getShortName();
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return strtolower($replaced ?? $shortName) . 's';
    }
}
