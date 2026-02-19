<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

interface ValueConverterInterface
{
    /**
     * Check if this converter supports the given value.
     */
    public function supports(mixed $value): bool;

    /**
     * Convert a PHP value to a database-compatible representation.
     */
    public function convertForDb(mixed $value): mixed;
}
