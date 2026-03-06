<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeCaster;

final class JsonTypeCaster implements TypeCasterInterface
{
    public function supports(string $type): bool
    {
        return $type === 'array';
    }

    public function cast(mixed $value, string $type): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Array type expects JSON string or array input.');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('JSON value for array type must decode to array.');
        }

        return $decoded;
    }
}
