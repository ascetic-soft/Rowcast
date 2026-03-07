<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Fixtures;

final class CardDto
{
    public string $id;
    public string $title;
    public ?array $publishData = null;
}
