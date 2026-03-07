<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeConverter;

final class TypeConverterRegistry implements TypeConverterInterface
{
    /** @var list<TypeConverterInterface> */
    private array $converters;

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

        return $this;
    }

    public function supports(string $phpType): bool
    {
        [$type] = $this->normalizeType($phpType);

        return array_any(
            $this->converters,
            static fn (TypeConverterInterface $converter): bool => $converter->supports($type),
        );
    }

    public function toPhp(mixed $value, string $phpType): mixed
    {
        [$type, $nullable] = $this->normalizeType($phpType);

        if ($nullable && $value === null) {
            return null;
        }

        foreach ($this->converters as $converter) {
            if ($converter->supports($type)) {
                return $converter->toPhp($value, $type);
            }
        }

        throw new \InvalidArgumentException(\sprintf('No type converter registered for type "%s".', $type));
    }

    public function toDb(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = get_debug_type($value);

        foreach ($this->converters as $converter) {
            if ($converter->supports($type)) {
                return $converter->toDb($value);
            }
        }

        return $value;
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
