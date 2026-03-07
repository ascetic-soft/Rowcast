<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Fixtures;

final class MixedUnionDto
{
    public mixed $payload;
    public int|string $unionValue;
}
