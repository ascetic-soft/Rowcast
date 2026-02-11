<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Mapping;

final class ResultSetMapping
{
    /**
     * Column-to-property mapping: [columnName => propertyName].
     *
     * @var array<string, string>
     */
    private array $fields = [];

    /**
     * @param class-string $className Fully-qualified DTO class name
     * @param string|null  $table     Database table name (optional, used by DataMapper for insert/update/delete)
     */
    public function __construct(
        private readonly string $className,
        private readonly ?string $table = null,
    ) {
    }

    /**
     * Creates a ResultSetMapping from an associative array.
     *
     * Expected format:
     * [
     *     'class'  => User::class,
     *     'table'  => 'users',          // optional
     *     'fields' => [
     *         'column_name' => 'propertyName',
     *     ],
     * ]
     *
     * @param array{class?: class-string, table?: string, fields?: array<string, string>} $config
     */
    public static function fromArray(array $config): self
    {
        if (!isset($config['class'])) {
            throw new \InvalidArgumentException('The "class" key is required in the configuration array.');
        }

        $rsm = new self($config['class'], $config['table'] ?? null);

        foreach ($config['fields'] ?? [] as $columnName => $propertyName) {
            $rsm->addField($columnName, $propertyName);
        }

        return $rsm;
    }

    /**
     * Maps a database column to a DTO property.
     *
     * @return $this
     */
    public function addField(string $columnName, string $propertyName): self
    {
        $this->fields[$columnName] = $propertyName;

        return $this;
    }

    /**
     * Returns the fully-qualified DTO class name.
     *
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Returns the database table name, or null if not set.
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Returns the column-to-property map.
     *
     * @return array<string, string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Returns the property name for a given column, or null if not mapped.
     */
    public function getPropertyName(string $columnName): ?string
    {
        return $this->fields[$columnName] ?? null;
    }

    /**
     * Returns the column name for a given property, or null if not mapped.
     */
    public function getColumnName(string $propertyName): ?string
    {
        $flipped = array_flip($this->fields);

        return $flipped[$propertyName] ?? null;
    }

    /**
     * Checks whether a mapping exists for the given column.
     */
    public function hasColumn(string $columnName): bool
    {
        return isset($this->fields[$columnName]);
    }

    /**
     * Checks whether a mapping exists for the given property.
     */
    public function hasProperty(string $propertyName): bool
    {
        return in_array($propertyName, $this->fields, true);
    }
}
