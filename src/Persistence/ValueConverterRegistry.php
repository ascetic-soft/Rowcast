<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

final class ValueConverterRegistry implements ValueConverterInterface
{
    /** @var list<ValueConverterInterface> */
    private array $converters;

    /**
     * @param list<ValueConverterInterface> $converters
     */
    public function __construct(array $converters = [])
    {
        $this->converters = $converters;
    }

    public static function createDefault(): self
    {
        return new self([
            new BoolValueConverter(),
            new EnumValueConverter(),
            new DateTimeValueConverter(),
        ]);
    }

    public function addConverter(ValueConverterInterface $converter): self
    {
        $this->converters[] = $converter;

        return $this;
    }

    public function supports(mixed $value): bool
    {
        return array_any($this->converters, static fn (ValueConverterInterface $c): bool => $c->supports($value));
    }

    public function convertForDb(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->converters as $converter) {
            if ($converter->supports($value)) {
                return $converter->convertForDb($value);
            }
        }

        return $value;
    }
}
