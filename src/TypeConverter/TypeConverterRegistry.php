<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

final class TypeConverterRegistry implements TypeConverterInterface
{
    /** @var list<TypeConverterInterface> */
    private array $converters;

    /** @var array<string, TypeConverterInterface|null> */
    private array $supportsCache = [];

    /** @var array<string, TypeConverterInterface|null> */
    private array $toPhpCache = [];

    /** @var array<string, TypeConverterInterface|null> */
    private array $toDbCache = [];

    /**
     * @param list<TypeConverterInterface> $converters
     */
    public function __construct(array $converters = [])
    {
        $this->converters = $converters;
    }

    public static function defaults(): self
    {
        return new self()
            ->add(new ScalarConverter())
            ->add(new BoolConverter())
            ->add(new DateTimeConverter())
            ->add(new JsonConverter())
            ->add(new EnumConverter());
    }

    public function add(TypeConverterInterface $converter): self
    {
        $this->converters[] = $converter;
        $this->supportsCache = [];
        $this->toPhpCache = [];
        $this->toDbCache = [];

        return $this;
    }

    public function supports(string $phpType): bool
    {
        [$type] = $this->normalizeType($phpType);

        return $this->resolveConverterForPhpType($type, $this->supportsCache) !== null;
    }

    public function toPhp(mixed $value, string $phpType): mixed
    {
        [$type, $nullable] = $this->normalizeType($phpType);

        if ($nullable && $value === null) {
            return null;
        }

        $converter = $this->resolveConverterForPhpType($type, $this->toPhpCache);
        if ($converter !== null) {
            return $converter->toPhp($value, $type);
        }

        throw new \InvalidArgumentException(\sprintf('No type converter registered for type "%s".', $type));
    }

    public function toDb(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = get_debug_type($value);

        $converter = $this->resolveConverterForPhpType($type, $this->toDbCache);
        if ($converter !== null) {
            return $converter->toDb($value);
        }

        return $value;
    }

    /**
     * @param array<string, TypeConverterInterface|null> $cache
     */
    private function resolveConverterForPhpType(string $type, array &$cache): ?TypeConverterInterface
    {
        if (\array_key_exists($type, $cache)) {
            return $cache[$type];
        }

        foreach ($this->converters as $converter) {
            if ($converter->supports($type)) {
                return $cache[$type] = $converter;
            }
        }

        return $cache[$type] = null;
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function normalizeType(string $phpType): array
    {
        if (str_starts_with($phpType, '?')) {
            return [substr($phpType, 1), true];
        }

        return [$phpType, false];
    }
}
