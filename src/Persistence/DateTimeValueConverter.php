<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

final class DateTimeValueConverter implements ValueConverterInterface
{
    public function __construct(
        private readonly string $format = 'Y-m-d H:i:sP',
    ) {
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof \DateTimeInterface;
    }

    public function convertForDb(mixed $value): string
    {
        static $utc;
        $utc ??= new \DateTimeZone('UTC');

        /** @var \DateTimeInterface $value */
        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone($utc)
            ->format($this->format);
    }
}
