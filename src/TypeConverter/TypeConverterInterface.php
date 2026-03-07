<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

interface TypeConverterInterface
{
    public function supports(string $phpType): bool;

    public function toPhp(mixed $value, string $phpType): mixed;

    public function toDb(mixed $value): mixed;
}
