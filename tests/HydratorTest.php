<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Hydrator;
use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\Tests\Fixtures\CardDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use PHPUnit\Framework\TestCase;

final class HydratorTest extends TestCase
{
    public function testHydrateAutoModeCastsTypes(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());
        $row = [
            'id' => '7',
            'email' => 'user@example.com',
            'is_active' => 1,
            'tags' => '["x","y"]',
            'created_at' => '2026-03-07 12:00:00+00:00',
            'status' => 'active',
            'previous_status' => null,
        ];

        $dto = $hydrator->hydrate(UserDto::class, $row);
        self::assertInstanceOf(UserDto::class, $dto);
        self::assertSame(7, $dto->id);
        self::assertTrue($dto->isActive);
        self::assertSame(['x', 'y'], $dto->tags);
        self::assertInstanceOf(\DateTimeImmutable::class, $dto->createdAt);
        self::assertSame(UserStatus::Active, $dto->status);
        self::assertNull($dto->previousStatus);
    }

    public function testHydrateAutoModeSupportsOverride(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());
        $mapping = Mapping::auto(CardDto::class, 'cards')
            ->column('keyword_meta', 'publishData');

        $dto = $hydrator->hydrate(CardDto::class, [
            'id' => '1',
            'title' => 'Title',
            'keyword_meta' => '{"a":1}',
        ], $mapping);

        self::assertSame(['a' => 1], $dto->publishData);
    }
}
