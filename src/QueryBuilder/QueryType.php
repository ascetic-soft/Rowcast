<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder;

enum QueryType
{
    case Select;
    case Insert;
    case Update;
    case Delete;
}
