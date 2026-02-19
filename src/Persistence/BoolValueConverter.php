<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

final class BoolValueConverter implements ValueConverterInterface
{
    public function supports(mixed $value): bool
    {
        return \is_bool($value);
    }

    public function convertForDb(mixed $value): int
    {
        return $value ? 1 : 0;
    }
}
