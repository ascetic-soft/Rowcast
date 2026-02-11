<?php

declare(strict_types=1);

namespace Rowcast\Tests\Mapping\NameConverter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowcast\Mapping\NameConverter\NameConverterInterface;
use Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;

final class SnakeCaseToCamelCaseConverterTest extends TestCase
{
    private SnakeCaseToCamelCaseConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new SnakeCaseToCamelCaseConverter();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(NameConverterInterface::class, $this->converter);
    }

    #[DataProvider('toPropertyNameProvider')]
    public function testToPropertyName(string $columnName, string $expectedPropertyName): void
    {
        self::assertSame($expectedPropertyName, $this->converter->toPropertyName($columnName));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function toPropertyNameProvider(): iterable
    {
        yield 'simple' => ['id', 'id'];
        yield 'single word' => ['name', 'name'];
        yield 'two words' => ['created_at', 'createdAt'];
        yield 'three words' => ['updated_by_user', 'updatedByUser'];
        yield 'already camelCase' => ['createdAt', 'createdAt'];
        yield 'leading underscore' => ['_private', 'private'];
        yield 'multiple underscores' => ['some__value', 'someValue'];
        yield 'all uppercase segments' => ['user_id', 'userId'];
    }

    #[DataProvider('toColumnNameProvider')]
    public function testToColumnName(string $propertyName, string $expectedColumnName): void
    {
        self::assertSame($expectedColumnName, $this->converter->toColumnName($propertyName));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function toColumnNameProvider(): iterable
    {
        yield 'simple' => ['id', 'id'];
        yield 'single word' => ['name', 'name'];
        yield 'two words' => ['createdAt', 'created_at'];
        yield 'three words' => ['updatedByUser', 'updated_by_user'];
        yield 'already snake_case' => ['created_at', 'created_at'];
    }

    #[DataProvider('roundTripProvider')]
    public function testRoundTripFromSnakeCase(string $snakeCase, string $camelCase): void
    {
        self::assertSame($camelCase, $this->converter->toPropertyName($snakeCase));
        self::assertSame($snakeCase, $this->converter->toColumnName($camelCase));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function roundTripProvider(): iterable
    {
        yield 'id' => ['id', 'id'];
        yield 'created_at / createdAt' => ['created_at', 'createdAt'];
        yield 'user_name / userName' => ['user_name', 'userName'];
        yield 'is_active / isActive' => ['is_active', 'isActive'];
        yield 'order_item_count / orderItemCount' => ['order_item_count', 'orderItemCount'];
    }
}
