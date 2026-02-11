<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\TypeCaster;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use AsceticSoft\Rowcast\TypeCaster\ScalarTypeCaster;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface;

final class ScalarTypeCasterTest extends TestCase
{
    private ScalarTypeCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new ScalarTypeCaster();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(TypeCasterInterface::class, $this->caster);
    }

    #[DataProvider('supportedTypesProvider')]
    public function testSupportsScalarTypes(string $type): void
    {
        self::assertTrue($this->caster->supports($type));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedTypesProvider(): iterable
    {
        yield 'int' => ['int'];
        yield 'float' => ['float'];
        yield 'bool' => ['bool'];
        yield 'string' => ['string'];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testDoesNotSupportNonScalarTypes(string $type): void
    {
        self::assertFalse($this->caster->supports($type));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedTypesProvider(): iterable
    {
        yield 'array' => ['array'];
        yield 'object' => ['object'];
        yield 'class name' => [\stdClass::class];
        yield 'integer (alias)' => ['integer'];
        yield 'boolean (alias)' => ['boolean'];
        yield 'double (alias)' => ['double'];
    }

    #[DataProvider('castToIntProvider')]
    public function testCastToInt(mixed $value, int $expected): void
    {
        self::assertSame($expected, $this->caster->cast($value, 'int'));
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function castToIntProvider(): iterable
    {
        yield 'string to int' => ['42', 42];
        yield 'float to int' => [3.14, 3];
        yield 'bool true to int' => [true, 1];
        yield 'bool false to int' => [false, 0];
        yield 'int unchanged' => [7, 7];
        yield 'negative string' => ['-5', -5];
        yield 'zero string' => ['0', 0];
    }

    #[DataProvider('castToFloatProvider')]
    public function testCastToFloat(mixed $value, float $expected): void
    {
        self::assertSame($expected, $this->caster->cast($value, 'float'));
    }

    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function castToFloatProvider(): iterable
    {
        yield 'string to float' => ['3.14', 3.14];
        yield 'int to float' => [42, 42.0];
        yield 'float unchanged' => [2.71, 2.71];
        yield 'zero string' => ['0', 0.0];
        yield 'negative string' => ['-1.5', -1.5];
    }

    #[DataProvider('castToBoolProvider')]
    public function testCastToBool(mixed $value, bool $expected): void
    {
        self::assertSame($expected, $this->caster->cast($value, 'bool'));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function castToBoolProvider(): iterable
    {
        yield 'truthy int' => [1, true];
        yield 'falsy int' => [0, false];
        yield 'truthy string' => ['1', true];
        yield 'empty string' => ['', false];
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
    }

    #[DataProvider('castToStringProvider')]
    public function testCastToString(mixed $value, string $expected): void
    {
        self::assertSame($expected, $this->caster->cast($value, 'string'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function castToStringProvider(): iterable
    {
        yield 'int to string' => [42, '42'];
        yield 'float to string' => [3.14, '3.14'];
        yield 'bool true to string' => [true, '1'];
        yield 'bool false to string' => [false, ''];
        yield 'string unchanged' => ['hello', 'hello'];
    }

    // --- Return type verification ---

    public function testCastToIntReturnsInt(): void
    {
        $result = $this->caster->cast('42', 'int');

        self::assertIsInt($result);
    }

    public function testCastToFloatReturnsFloat(): void
    {
        $result = $this->caster->cast('3.14', 'float');

        self::assertIsFloat($result);
    }

    public function testCastToBoolReturnsBool(): void
    {
        $result = $this->caster->cast(1, 'bool');

        self::assertIsBool($result);
    }

    public function testCastToStringReturnsString(): void
    {
        $result = $this->caster->cast(42, 'string');

        self::assertIsString($result);
    }

    // --- Null-like string casting ---

    public function testCastNullStringToInt(): void
    {
        self::assertSame(0, $this->caster->cast('', 'int'));
    }

    public function testCastNullStringToFloat(): void
    {
        self::assertSame(0.0, $this->caster->cast('', 'float'));
    }

    public function testCastNullStringToBool(): void
    {
        self::assertFalse($this->caster->cast('', 'bool'));
    }
}
