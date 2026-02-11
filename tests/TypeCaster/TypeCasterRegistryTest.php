<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\TypeCaster;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use AsceticSoft\Rowcast\TypeCaster\DateTimeTypeCaster;
use AsceticSoft\Rowcast\TypeCaster\EnumTypeCaster;
use AsceticSoft\Rowcast\TypeCaster\ScalarTypeCaster;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;

final class TypeCasterRegistryTest extends TestCase
{
    private TypeCasterRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = TypeCasterRegistry::createDefault();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(TypeCasterInterface::class, $this->registry);
    }

    public function testCreateDefaultIncludesAllBuiltInCasters(): void
    {
        self::assertTrue($this->registry->supports('int'));
        self::assertTrue($this->registry->supports('float'));
        self::assertTrue($this->registry->supports('bool'));
        self::assertTrue($this->registry->supports('string'));
        self::assertTrue($this->registry->supports(DateTimeImmutable::class));
        self::assertTrue($this->registry->supports(\DateTime::class));
        self::assertTrue($this->registry->supports(Status::class));
        self::assertTrue($this->registry->supports(Priority::class));
    }

    public function testDoesNotSupportUnknownType(): void
    {
        self::assertFalse($this->registry->supports(\stdClass::class));
    }

    // --- Scalar casting through registry ---

    #[DataProvider('scalarCastProvider')]
    public function testCastScalarTypes(mixed $value, string $type, mixed $expected): void
    {
        self::assertSame($expected, $this->registry->cast($value, $type));
    }

    /**
     * @return iterable<string, array{mixed, string, mixed}>
     */
    public static function scalarCastProvider(): iterable
    {
        yield 'string to int' => ['42', 'int', 42];
        yield 'string to float' => ['3.14', 'float', 3.14];
        yield 'int to bool' => [1, 'bool', true];
        yield 'int to string' => [42, 'string', '42'];
    }

    // --- DateTime casting through registry ---

    public function testCastDateTimeImmutable(): void
    {
        $result = $this->registry->cast('2025-06-15 10:30:00', DateTimeImmutable::class);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2025-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    // --- Enum casting through registry ---

    public function testCastStringBackedEnum(): void
    {
        $result = $this->registry->cast('active', Status::class);

        self::assertSame(Status::Active, $result);
    }

    public function testCastIntBackedEnum(): void
    {
        $result = $this->registry->cast(2, Priority::class);

        self::assertSame(Priority::Medium, $result);
    }

    // --- Nullable handling ---

    public function testSupportsNullableType(): void
    {
        self::assertTrue($this->registry->supports('?int'));
        self::assertTrue($this->registry->supports('?string'));
        self::assertTrue($this->registry->supports('?' . DateTimeImmutable::class));
        self::assertTrue($this->registry->supports('?' . Status::class));
    }

    public function testCastNullableReturnsNullWhenValueIsNull(): void
    {
        self::assertNull($this->registry->cast(null, '?int'));
        self::assertNull($this->registry->cast(null, '?string'));
        self::assertNull($this->registry->cast(null, '?' . DateTimeImmutable::class));
        self::assertNull($this->registry->cast(null, '?' . Status::class));
    }

    public function testCastNullableCastsNonNullValue(): void
    {
        self::assertSame(42, $this->registry->cast('42', '?int'));
        self::assertSame('hello', $this->registry->cast('hello', '?string'));
    }

    // --- Exception on unsupported type ---

    public function testCastUnsupportedTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No type caster registered for type "stdClass"');

        $this->registry->cast('value', \stdClass::class);
    }

    // --- Empty constructor + addCaster ---

    public function testEmptyRegistrySupportNothing(): void
    {
        $registry = new TypeCasterRegistry();

        self::assertFalse($registry->supports('int'));
        self::assertFalse($registry->supports('string'));
    }

    public function testAddCasterReturnsSelfForFluency(): void
    {
        $registry = new TypeCasterRegistry();
        $result = $registry->addCaster(new ScalarTypeCaster());

        self::assertSame($registry, $result);
    }

    public function testAddCasterMakesTypeSupported(): void
    {
        $registry = new TypeCasterRegistry();
        $registry->addCaster(new ScalarTypeCaster());

        self::assertTrue($registry->supports('int'));
        self::assertSame(42, $registry->cast('42', 'int'));
    }

    public function testCustomCasterTakesPrecedenceWhenAddedFirst(): void
    {
        $custom = new class implements TypeCasterInterface {
            public function supports(string $type): bool
            {
                return $type === 'int';
            }

            public function cast(mixed $value, string $type): mixed
            {
                return -1; // always returns -1
            }
        };

        $registry = new TypeCasterRegistry([$custom, new ScalarTypeCaster()]);

        self::assertSame(-1, $registry->cast('42', 'int'));
    }

    // --- Nullable unsupported type ---

    public function testDoesNotSupportNullableUnsupportedType(): void
    {
        self::assertFalse($this->registry->supports('?' . \stdClass::class));
    }

    public function testCastNullableUnsupportedTypeThrowsForNonNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No type caster registered for type "stdClass"');

        $this->registry->cast('value', '?' . \stdClass::class);
    }

    public function testCastNullableUnsupportedTypeReturnsNullForNull(): void
    {
        // Even though stdClass is not supported, null value with nullable type returns null
        $result = $this->registry->cast(null, '?' . \stdClass::class);

        self::assertNull($result);
    }

    // --- addCaster with multiple casters ---

    public function testAddMultipleCasters(): void
    {
        $registry = new TypeCasterRegistry();
        $registry->addCaster(new ScalarTypeCaster());
        $registry->addCaster(new DateTimeTypeCaster());

        self::assertTrue($registry->supports('int'));
        self::assertTrue($registry->supports(DateTimeImmutable::class));
        self::assertFalse($registry->supports(Status::class)); // Enum not added
    }

    // --- createDefault returns separate instances ---

    public function testCreateDefaultReturnsSeparateInstances(): void
    {
        $reg1 = TypeCasterRegistry::createDefault();
        $reg2 = TypeCasterRegistry::createDefault();

        self::assertNotSame($reg1, $reg2);
    }

    // --- Cast nullable DateTime ---

    public function testCastNullableDateTimeWithValue(): void
    {
        $result = $this->registry->cast('2025-06-15 10:30:00', '?' . DateTimeImmutable::class);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2025-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCastNullableDateTimeWithNull(): void
    {
        $result = $this->registry->cast(null, '?' . DateTimeImmutable::class);

        self::assertNull($result);
    }

    // --- Cast nullable enum ---

    public function testCastNullableEnumWithValue(): void
    {
        $result = $this->registry->cast('active', '?' . Status::class);

        self::assertSame(Status::Active, $result);
    }

    public function testCastNullableEnumWithNull(): void
    {
        $result = $this->registry->cast(null, '?' . Status::class);

        self::assertNull($result);
    }
}
