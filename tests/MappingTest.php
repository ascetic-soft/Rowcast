<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\Tests\Fixtures\CardDto;
use PHPUnit\Framework\TestCase;

final class MappingTest extends TestCase
{
    public function testAutoMappingSupportsOverridesAndIgnoredProperties(): void
    {
        $mapping = Mapping::auto(CardDto::class, 'cards')
            ->column('keyword_meta', 'publishData')
            ->ignore('title');

        self::assertTrue($mapping->isAutoDiscover());
        self::assertSame(CardDto::class, $mapping->getClassName());
        self::assertSame('cards', $mapping->getTable());
        self::assertSame('publishData', $mapping->getPropertyForColumn('keyword_meta'));
        self::assertSame('keyword_meta', $mapping->getColumnForProperty('publishData'));
        self::assertTrue($mapping->isIgnored('title'));
    }

    public function testExplicitMappingDisablesAutoDiscovery(): void
    {
        $mapping = Mapping::explicit(CardDto::class, 'cards')
            ->column('id', 'id')
            ->column('keyword_meta', 'publishData');

        self::assertFalse($mapping->isAutoDiscover());
        self::assertSame(['id' => 'id', 'keyword_meta' => 'publishData'], $mapping->getColumns());
    }

    public function testResolvePropertiesForExplicitSkipsIgnoredAndMissingProperties(): void
    {
        $mapping = Mapping::explicit(CardDto::class, 'cards')
            ->column('id', 'id')
            ->column('missing_column', 'missingProperty')
            ->column('title', 'title')
            ->ignore('title');

        $resolved = Mapping::resolvePropertiesFor(
            $mapping,
            new \ReflectionClass(CardDto::class),
            new SnakeCaseToCamelCase(),
        );

        self::assertSame(['id' => 'id'], $resolved);
    }

    public function testResolvePropertiesForAutoModeReturnsConvertedColumns(): void
    {
        $resolved = Mapping::resolvePropertiesFor(
            null,
            new \ReflectionClass(CardDto::class),
            new SnakeCaseToCamelCase(),
        );

        self::assertArrayHasKey('publish_data', $resolved);
        self::assertSame('publishData', $resolved['publish_data']);
    }
}
