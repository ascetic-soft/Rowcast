<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

final class ScalarConverter implements TypeConverterInterface
{
    private const array SUPPORTED_TYPES = ['int', 'float', 'string'];

    public function supports(string $phpType): bool
    {
        return \in_array($phpType, self::SUPPORTED_TYPES, true);
    }

    public function toPhp(mixed $value, string $phpType): int|float|string
    {
        if (!\is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException(\sprintf('ScalarConverter expects scalar value, got %s.', get_debug_type($value)));
        }

        $scalarValue = $value;

        return match ($phpType) {
            'int' => (int) $scalarValue,
            'float' => (float) $scalarValue,
            'string' => (string) $scalarValue,
            default => throw new \InvalidArgumentException('Unsupported scalar type: ' . $phpType),
        };
    }

    public function toDb(mixed $value): mixed
    {
        return $value;
    }
}
