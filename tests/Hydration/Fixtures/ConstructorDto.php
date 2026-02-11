<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Hydration\Fixtures;

/**
 * DTO with a constructor that has required parameters.
 * Used to verify that the hydrator creates objects without calling the constructor.
 */
final class ConstructorDto
{
    public int $id;
    public string $name;
    public bool $constructorCalled = false;

    public function __construct(string $requiredParam)
    {
        $this->constructorCalled = true;
    }
}
