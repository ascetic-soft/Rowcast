<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\NameConverter;

interface NameConverterInterface
{
    public function toPropertyName(string $columnName): string;

    public function toColumnName(string $propertyName): string;
}
