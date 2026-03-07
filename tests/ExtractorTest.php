<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Extractor;
use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\Tests\Fixtures\CardDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use PHPUnit\Framework\TestCase;

final class ExtractorTest extends TestCase
{
    public function testExtractAutoModeConvertsTypes(): void
    {
        $dto = new UserDto();
        $dto->id = 7;
        $dto->email = 'u@x.com';
        $dto->isActive = true;
        $dto->tags = ['t1', 't2'];
        $dto->createdAt = new \DateTimeImmutable('2026-03-07 12:00:00+03:00');
        $dto->status = UserStatus::Inactive;
        $dto->previousStatus = null;

        $data = new Extractor()->extract($dto);

        self::assertSame(7, $data['id']);
        self::assertSame('u@x.com', $data['email']);
        self::assertSame(1, $data['is_active']);
        self::assertSame('["t1","t2"]', $data['tags']);
        self::assertSame('inactive', $data['status']);
        self::assertArrayHasKey('created_at', $data);
        self::assertArrayHasKey('previous_status', $data);
        self::assertNull($data['previous_status']);
    }

    public function testExtractAutoModeSupportsOverrideAndIgnore(): void
    {
        $dto = new CardDto();
        $dto->id = 'c1';
        $dto->title = 'Card';
        $dto->publishData = ['a' => 1];

        $mapping = Mapping::auto(CardDto::class, 'cards')
            ->column('keyword_meta', 'publishData')
            ->ignore('title');

        $data = new Extractor()->extract($dto, $mapping);

        self::assertSame('c1', $data['id']);
        self::assertSame('{"a":1}', $data['keyword_meta']);
        self::assertArrayNotHasKey('title', $data);
    }
}
