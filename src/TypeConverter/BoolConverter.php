<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

final class BoolConverter implements TypeConverterInterface
{
    public function supports(string $phpType): bool
    {
        return $phpType === 'bool';
    }

    public function toPhp(mixed $value, string $phpType): bool
    {
        return (bool) $value;
    }

    public function toDb(mixed $value): int
    {
        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('BoolConverter expects bool.');
        }

        return $value ? 1 : 0;
    }
}
