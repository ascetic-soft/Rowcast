<?php

declare(strict_types=1);

namespace Rowcast\Tests\Mapping\NameConverter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowcast\Mapping\NameConverter\NameConverterInterface;
use Rowcast\Mapping\NameConverter\NullConverter;

final class NullConverterTest extends TestCase
{
    private NullConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new NullConverter();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(NameConverterInterface::class, $this->converter);
    }

    #[DataProvider('nameProvider')]
    public function testToPropertyNameReturnsInputUnchanged(string $name): void
    {
        self::assertSame($name, $this->converter->toPropertyName($name));
    }

    #[DataProvider('nameProvider')]
    public function testToColumnNameReturnsInputUnchanged(string $name): void
    {
        self::assertSame($name, $this->converter->toColumnName($name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nameProvider(): iterable
    {
        yield 'simple' => ['id'];
        yield 'snake_case' => ['created_at'];
        yield 'camelCase' => ['createdAt'];
        yield 'PascalCase' => ['CreatedAt'];
        yield 'empty string' => [''];
    }
}
