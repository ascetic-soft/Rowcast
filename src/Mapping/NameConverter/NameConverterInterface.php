<?php

declare(strict_types=1);

namespace Rowcast\Mapping\NameConverter;

interface NameConverterInterface
{
    /**
     * Converts a database column name to a PHP property name.
     */
    public function toPropertyName(string $columnName): string;

    /**
     * Converts a PHP property name to a database column name.
     */
    public function toColumnName(string $propertyName): string;
}
