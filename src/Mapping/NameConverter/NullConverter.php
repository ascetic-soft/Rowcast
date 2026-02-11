<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Mapping\NameConverter;

final class NullConverter implements NameConverterInterface
{
    public function toPropertyName(string $columnName): string
    {
        return $columnName;
    }

    public function toColumnName(string $propertyName): string
    {
        return $propertyName;
    }
}
