<?php

declare(strict_types=1);

namespace Rowcast\TypeCaster;

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
            return $value;
        }

        /** @var class-string<BackedEnum> $type */
        return $type::from($value);
    }
}
