<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeCaster;

final class TypeCasterRegistry implements TypeCasterInterface
{
    /** @var list<TypeCasterInterface> */
    private array $casters;

    /**
     * @param list<TypeCasterInterface> $casters
     */
    public function __construct(array $casters = [])
    {
        $this->casters = $casters;
    }

    /**
     * Create a registry with the default built-in casters.
     */
    public static function createDefault(): self
    {
        return new self([
            new ScalarTypeCaster(),
            new DateTimeTypeCaster(),
            new EnumTypeCaster(),
        ]);
    }

    public function addCaster(TypeCasterInterface $caster): self
    {
        $this->casters[] = $caster;

        return $this;
    }

    public function supports(string $type): bool
    {
        if ($this->isNullable($type)) {
            $type = $this->stripNullable($type);
        }

        return array_any($this->casters, fn ($caster) => $caster->supports($type));
    }

    public function cast(mixed $value, string $type): mixed
    {
        $nullable = $this->isNullable($type);

        if ($nullable) {
            $type = $this->stripNullable($type);
        }

        if ($nullable && $value === null) {
            return null;
        }

        foreach ($this->casters as $caster) {
            if ($caster->supports($type)) {
                return $caster->cast($value, $type);
            }
        }

        throw new \InvalidArgumentException(\sprintf('No type caster registered for type "%s".', $type));
    }

    private function isNullable(string $type): bool
    {
        return str_starts_with($type, '?');
    }

    private function stripNullable(string $type): string
    {
        return ltrim($type, '?');
    }
}
