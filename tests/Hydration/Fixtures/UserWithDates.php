<?php

declare(strict_types=1);

namespace Rowcast\Tests\Hydration\Fixtures;

use DateTimeImmutable;

final class UserWithDates
{
    public int $id;
    public string $name;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;
}
