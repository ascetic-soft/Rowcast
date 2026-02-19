<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

final class EnumValueConverter implements ValueConverterInterface
{
    public function supports(mixed $value): bool
    {
        return $value instanceof \BackedEnum;
    }

    public function convertForDb(mixed $value): int|string
    {
        /** @var \BackedEnum $value */
        return $value->value;
    }
}
