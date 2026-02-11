<?php

declare(strict_types=1);

namespace Rowcast\TypeCaster;

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
        if ($value instanceof $type) {
            return $value;
        }

        return match ($type) {
            DateTimeImmutable::class => new DateTimeImmutable((string) $value),
            DateTime::class => new DateTime((string) $value),
        };
    }
}
