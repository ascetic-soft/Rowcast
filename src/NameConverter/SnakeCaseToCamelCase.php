<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\NameConverter;

final class SnakeCaseToCamelCase implements NameConverterInterface
{
    public function toPropertyName(string $columnName): string
    {
        return lcfirst(str_replace('_', '', ucwords($columnName, '_')));
    }

    public function toColumnName(string $propertyName): string
    {
        $replaced = preg_replace('/[A-Z]/', '_$0', $propertyName);

        return strtolower($replaced ?? $propertyName);
    }
}
