<?php

declare(strict_types=1);

namespace Rowcast\Tests\Hydration\Fixtures;

use DateTimeImmutable;

final class NullableDto
{
    public int $id;
    public ?string $nickname;
    public ?DateTimeImmutable $deletedAt;
}
