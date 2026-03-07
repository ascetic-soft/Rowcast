<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

final readonly class DateTimeConverter implements TypeConverterInterface
{
    public function __construct(
        private string $format = 'Y-m-d H:i:sP',
    ) {
    }

    public function supports(string $phpType): bool
    {
        return $phpType === \DateTimeImmutable::class
            || $phpType === \DateTime::class
            || $phpType === \DateTimeInterface::class
            || is_a($phpType, \DateTimeInterface::class, true);
    }

    public function toPhp(mixed $value, string $phpType): \DateTimeImmutable|\DateTime
    {
        if ($value instanceof \DateTimeImmutable) {
            return $phpType === \DateTime::class
                ? \DateTime::createFromImmutable($value)
                : $value;
        }

        if ($value instanceof \DateTime) {
            if ($phpType === \DateTimeImmutable::class || $phpType === \DateTimeInterface::class) {
                return \DateTimeImmutable::createFromMutable($value);
            }

            return $value;
        }

        if (!\is_scalar($value)) {
            throw new \InvalidArgumentException('DateTimeConverter expects scalar or DateTime value.');
        }

        $stringValue = (string) $value;

        return match ($phpType) {
            \DateTime::class => new \DateTime($stringValue),
            default => new \DateTimeImmutable($stringValue),
        };
    }

    public function toDb(mixed $value): string
    {
        if (!$value instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('DateTimeConverter expects DateTimeInterface.');
        }

        static $utc;
        $utc ??= new \DateTimeZone('UTC');

        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone($utc)
            ->format($this->format);
    }
}
