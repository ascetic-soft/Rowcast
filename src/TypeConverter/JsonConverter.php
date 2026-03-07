<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

final class JsonConverter implements TypeConverterInterface
{
    public function supports(string $phpType): bool
    {
        return $phpType === 'array';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toPhp(mixed $value, string $phpType): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('JsonConverter expects JSON string or array input.');
        }

        $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('JsonConverter decoded value must be an array.');
        }

        return $decoded;
    }

    public function toDb(mixed $value): string
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('JsonConverter expects array.');
        }

        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
