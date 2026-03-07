<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Hydrator;
use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\Tests\Fixtures\CardDto;
use AsceticSoft\Rowcast\Tests\Fixtures\MixedUnionDto;
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

    public function testHydrateAllHydratesEachRow(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());

        $rows = [
            ['id' => '1', 'email' => 'a@example.com', 'is_active' => 1, 'tags' => '[]', 'status' => 'active'],
            ['id' => '2', 'email' => 'b@example.com', 'is_active' => 0, 'tags' => '[]', 'status' => 'inactive'],
        ];

        $result = $hydrator->hydrateAll(UserDto::class, $rows);

        self::assertCount(2, $result);
        self::assertSame(1, $result[0]->id);
        self::assertSame(2, $result[1]->id);
    }

    public function testHydrateDoesNotConvertMixedAndUnionTypes(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());

        $dto = $hydrator->hydrate(MixedUnionDto::class, [
            'payload' => ['k' => 'v'],
            'union_value' => '7',
        ]);

        self::assertSame(['k' => 'v'], $dto->payload);
        self::assertSame('7', $dto->unionValue);
    }
}
