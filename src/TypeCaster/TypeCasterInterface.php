<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\TypeCaster;

interface TypeCasterInterface
{
    /**
     * Check if this caster supports the given type.
     */
    public function supports(string $type): bool;

    /**
     * Cast a raw value to the given type.
     *
     * @param mixed  $value The raw value from the database
     * @param string $type  The target PHP type name
     *
     * @return mixed The cast value
     */
    public function cast(mixed $value, string $type): mixed;
}
