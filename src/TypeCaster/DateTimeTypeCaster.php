<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeCaster;

use DateTimeImmutable;
use DateTime;

final class DateTimeTypeCaster implements TypeCasterInterface
{
    private const SUPPORTED_TYPES = [
        DateTimeImmutable::class,
        DateTime::class,
    ];

    public function supports(string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES, true);
    }

    public function cast(mixed $value, string $type): DateTimeImmutable|DateTime
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTime) {
            return $value;
        }

        $strValue = is_scalar($value) || $value === null ? (string) $value : '';

        return match ($type) {
            DateTimeImmutable::class => new DateTimeImmutable($strValue),
            DateTime::class => new DateTime($strValue),
            default => throw new \InvalidArgumentException('Unsupported type: ' . $type),
        };
    }
}
