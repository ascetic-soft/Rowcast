<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

final class DateTimeValueConverter implements ValueConverterInterface
{
    public function __construct(
        private readonly string $format = 'Y-m-d H:i:s',
    ) {
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof \DateTimeInterface;
    }

    public function convertForDb(mixed $value): string
    {
        /** @var \DateTimeInterface $value */
        return $value->format($this->format);
    }
}
