<?php

declare(strict_types=1);

namespace Rowcast\TypeCaster;

final class ScalarTypeCaster implements TypeCasterInterface
{
    private const SUPPORTED_TYPES = ['int', 'float', 'bool', 'string'];

    public function supports(string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES, true);
    }

    public function cast(mixed $value, string $type): int|float|bool|string
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
        };
    }
}
