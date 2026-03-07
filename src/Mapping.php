<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

final class Mapping
{
    /** @var array<string, string> column => property */
    private array $columns = [];

    /** @var array<string, string>|null property => column */
    private ?array $propertyToColumn = null;

    /** @var array<string, true> */
    private array $ignored = [];

    /**
     * @param class-string $className
     */
    private function __construct(
        private readonly string $className,
        private readonly string $table,
        private readonly bool $autoDiscover,
    ) {
    }

    /**
     * @param class-string $className
     */
    public static function auto(string $className, string $table): self
    {
        return new self($className, $table, true);
    }

    /**
     * @param class-string $className
     */
    public static function explicit(string $className, string $table): self
    {
        return new self($className, $table, false);
    }

    public function column(string $columnName, string $propertyName): self
    {
        $this->columns[$columnName] = $propertyName;
        $this->propertyToColumn = null;

        return $this;
    }

    public function ignore(string ...$properties): self
    {
        foreach ($properties as $property) {
            $this->ignored[$property] = true;
        }

        return $this;
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function isAutoDiscover(): bool
    {
        return $this->autoDiscover;
    }

    /**
     * @return array<string, string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function isIgnored(string $propertyName): bool
    {
        return isset($this->ignored[$propertyName]);
    }

    public function getPropertyForColumn(string $columnName): ?string
    {
        return $this->columns[$columnName] ?? null;
    }

    public function getColumnForProperty(string $propertyName): ?string
    {
        $this->propertyToColumn ??= array_flip($this->columns);

        return $this->propertyToColumn[$propertyName] ?? null;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return array<string, string> column => property
     */
    public static function resolvePropertiesFor(
        ?self $mapping,
        \ReflectionClass $reflectionClass,
        NameConverterInterface $nameConverter,
    ): array {
        $result = [];
        if ($mapping !== null && !$mapping->isAutoDiscover()) {
            foreach ($mapping->getColumns() as $columnName => $propertyName) {
                if ($mapping->isIgnored($propertyName) || !$reflectionClass->hasProperty($propertyName)) {
                    continue;
                }

                $result[$columnName] = $propertyName;
            }

            return $result;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            if ($mapping?->isIgnored($propertyName) === true) {
                continue;
            }

            $columnName = $mapping?->getColumnForProperty($propertyName)
                ?? $nameConverter->toColumnName($propertyName);
            $result[$columnName] = $propertyName;
        }

        return $result;
    }
}
