<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeCaster;

final class DateTimeTypeCaster implements TypeCasterInterface
{
    private const array SUPPORTED_TYPES = [
        \DateTimeImmutable::class,
        \DateTimeInterface::class,
        \DateTime::class,
    ];

    public function supports(string $type): bool
    {
        return \in_array($type, self::SUPPORTED_TYPES, true);
    }

    public function cast(mixed $value, string $type): \DateTimeImmutable|\DateTime
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            if ($type === \DateTimeImmutable::class || $type === \DateTimeInterface::class) {
                return \DateTimeImmutable::createFromMutable($value);
            }

            return $value;
        }

        $strValue = \is_scalar($value) || $value === null ? (string) $value : '';

        return match ($type) {
            \DateTimeImmutable::class, \DateTimeInterface::class => new \DateTimeImmutable($strValue),
            \DateTime::class => new \DateTime($strValue),
            default => throw new \InvalidArgumentException('Unsupported type: ' . $type),
        };
    }
}
