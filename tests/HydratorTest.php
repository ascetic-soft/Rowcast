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

    public function testHydrateUsesUpdatedMappingConfigurationAcrossRepeatedCalls(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());
        $mapping = Mapping::auto(CardDto::class, 'cards')
            ->column('keyword_meta', 'publishData');

        $first = $hydrator->hydrate(CardDto::class, [
            'id' => '1',
            'title' => 'Title',
            'keyword_meta' => '{"a":1}',
        ], $mapping);

        self::assertSame(['a' => 1], $first->publishData);

        $mapping->ignore('publishData');

        $second = $hydrator->hydrate(CardDto::class, [
            'id' => '2',
            'title' => 'Title 2',
            'keyword_meta' => '{"b":2}',
        ], $mapping);

        self::assertFalse(isset($second->publishData));
    }

    public function testHydrateRepeatedlyPreservesTypedConversions(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());
        $row = [
            'id' => '8',
            'email' => 'repeat@example.com',
            'is_active' => 0,
            'tags' => '["z"]',
            'created_at' => null,
            'status' => 'inactive',
            'previous_status' => 'active',
        ];

        $first = $hydrator->hydrate(UserDto::class, $row);
        $second = $hydrator->hydrate(UserDto::class, $row);

        self::assertSame(8, $first->id);
        self::assertFalse($first->isActive);
        self::assertNull($first->createdAt);
        self::assertSame(UserStatus::Inactive, $first->status);
        self::assertSame(UserStatus::Active, $first->previousStatus);

        self::assertSame(8, $second->id);
        self::assertFalse($second->isActive);
        self::assertNull($second->createdAt);
        self::assertSame(UserStatus::Inactive, $second->status);
        self::assertSame(UserStatus::Active, $second->previousStatus);
    }

    public function testHydrateIterableHydratesRowsWithCachedMetadata(): void
    {
        $hydrator = new Hydrator(TypeConverterRegistry::defaults(), new SnakeCaseToCamelCase());
        $mapping = Mapping::auto(CardDto::class, 'cards')
            ->column('keyword_meta', 'publishData');

        $rows = [
            ['id' => '1', 'title' => 'First', 'keyword_meta' => '{"a":1}'],
            ['id' => '2', 'title' => 'Second', 'keyword_meta' => '{"b":2}'],
        ];

        $result = iterator_to_array($hydrator->hydrateIterable(CardDto::class, $rows, $mapping));

        self::assertCount(2, $result);
        self::assertSame(['a' => 1], $result[0]->publishData);
        self::assertSame(['b' => 2], $result[1]->publishData);
    }
}
