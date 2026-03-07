<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Fixtures;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
