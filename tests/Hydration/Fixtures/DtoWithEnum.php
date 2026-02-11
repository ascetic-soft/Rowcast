<?php

declare(strict_types=1);

namespace Rowcast\Tests\Hydration\Fixtures;

final class DtoWithEnum
{
    public int $id;
    public UserStatus $status;
    public ?UserStatus $previousStatus;
}
