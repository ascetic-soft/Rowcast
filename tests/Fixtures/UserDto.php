<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Fixtures;

final class UserDto
{
    public int $id;
    public string $email;
    public bool $isActive;
    /** @var array<int, string> */
    public array $tags = [];
    public ?\DateTimeImmutable $createdAt = null;
    public UserStatus $status;
    public ?UserStatus $previousStatus = null;
}
