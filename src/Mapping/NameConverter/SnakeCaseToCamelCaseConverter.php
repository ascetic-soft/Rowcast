<?php

declare(strict_types=1);

namespace Rowcast\Mapping\NameConverter;

final class SnakeCaseToCamelCaseConverter implements NameConverterInterface
{
    /**
     * Converts a snake_case column name to a camelCase property name.
     *
     * Examples: "created_at" -> "createdAt", "user_name" -> "userName", "id" -> "id"
     */
    public function toPropertyName(string $columnName): string
    {
        return lcfirst(str_replace('_', '', ucwords($columnName, '_')));
    }

    /**
     * Converts a camelCase property name to a snake_case column name.
     *
     * Examples: "createdAt" -> "created_at", "userName" -> "user_name", "id" -> "id"
     */
    public function toColumnName(string $propertyName): string
    {
        $replaced = preg_replace('/[A-Z]/', '_$0', $propertyName);

        return strtolower($replaced ?? $propertyName);
    }
}
