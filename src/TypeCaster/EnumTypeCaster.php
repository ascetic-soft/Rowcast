<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeCaster;

use BackedEnum;

final class EnumTypeCaster implements TypeCasterInterface
{
    public function supports(string $type): bool
    {
        return enum_exists($type) && is_subclass_of($type, BackedEnum::class);
    }

    public function cast(mixed $value, string $type): BackedEnum
    {
        if ($value instanceof $type) {
            /** @var BackedEnum $value */
            return $value;
        }

        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException('Enum value must be int or string, got ' . get_debug_type($value));
        }

        /** @var class-string<BackedEnum> $type */
        return $type::from($value);
    }
}
