<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Hydration\Fixtures;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';
}
