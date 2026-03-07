<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

final class Mapping
{
    /** @var array<string, string> column => property */
    private array $columns = [];

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
        $columns = array_flip($this->columns);

        return $columns[$propertyName] ?? null;
    }
}
