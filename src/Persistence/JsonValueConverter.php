<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

final class JsonValueConverter implements ValueConverterInterface
{
    public function supports(mixed $value): bool
    {
        return \is_array($value);
    }

    public function convertForDb(mixed $value): string
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('JsonValueConverter expects array.');
        }

        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
