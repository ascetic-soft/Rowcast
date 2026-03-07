<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

use BackedEnum;

final class EnumConverter implements TypeConverterInterface
{
    public function supports(string $phpType): bool
    {
        return enum_exists($phpType) && is_subclass_of($phpType, BackedEnum::class);
    }

    public function toPhp(mixed $value, string $phpType): BackedEnum
    {
        if ($value instanceof $phpType) {
            /** @var BackedEnum $value */
            return $value;
        }

        if (!\is_int($value) && !\is_string($value)) {
            throw new \InvalidArgumentException('EnumConverter expects int|string for enum hydration.');
        }

        /** @var class-string<BackedEnum> $phpType */
        return $phpType::from($value);
    }

    public function toDb(mixed $value): int|string
    {
        if (!$value instanceof BackedEnum) {
            throw new \InvalidArgumentException('EnumConverter expects BackedEnum.');
        }

        return $value->value;
    }
}
