<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\TargetResolver;
use AsceticSoft\Rowcast\Tests\Fixtures\CardDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserDto;
use PHPUnit\Framework\TestCase;

final class TargetResolverTest extends TestCase
{
    public function testResolveTargetFromMappingAndClassNameAndTableAlias(): void
    {
        $resolver = new TargetResolver(new SnakeCaseToCamelCase());
        $mapping = Mapping::auto(CardDto::class, 'cards');

        self::assertSame(['cards', CardDto::class, $mapping], $resolver->resolveTarget($mapping));
        self::assertSame(['user_dtos', UserDto::class, null], $resolver->resolveTarget(UserDto::class));

        $dto = new CardDto();
        self::assertSame(['custom_table', CardDto::class, null], $resolver->resolveTarget('custom_table', $dto));
    }

    public function testResolveTargetThrowsForUnknownClassWithoutDto(): void
    {
        $resolver = new TargetResolver(new SnakeCaseToCamelCase());

        $this->expectException(\LogicException::class);
        $resolver->resolveTarget('unknown_class');
    }

    public function testBuildWhereFromIdentityPropertiesAndResolveColumnName(): void
    {
        $resolver = new TargetResolver(new SnakeCaseToCamelCase());
        $mapping = Mapping::auto(CardDto::class, 'cards')->column('keyword_meta', 'publishData');

        $where = $resolver->buildWhereFromIdentityProperties(
            ['id', 'publishData'],
            ['id' => 'c1', 'keyword_meta' => '{"k":"v"}'],
            $mapping,
        );

        self::assertSame(['id' => 'c1', 'keyword_meta' => '{"k":"v"}'], $where);
        self::assertSame('keyword_meta', $resolver->resolveColumnName('publishData', $mapping));
        self::assertSame('created_at', $resolver->resolveColumnName('createdAt', null));
    }

    public function testBuildWhereFromIdentityPropertiesThrowsWhenPropertyNotExtracted(): void
    {
        $resolver = new TargetResolver(new SnakeCaseToCamelCase());

        $this->expectException(\LogicException::class);
        $resolver->buildWhereFromIdentityProperties(['id'], ['email' => 'x@y.z'], null);
    }
}
