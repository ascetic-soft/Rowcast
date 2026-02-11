<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\TypeCaster;

use PHPUnit\Framework\TestCase;
use AsceticSoft\Rowcast\TypeCaster\EnumTypeCaster;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum Priority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}

enum Color
{
    case Red;
    case Green;
    case Blue;
}

final class EnumTypeCasterTest extends TestCase
{
    private EnumTypeCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new EnumTypeCaster();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(TypeCasterInterface::class, $this->caster);
    }

    public function testSupportsStringBackedEnum(): void
    {
        self::assertTrue($this->caster->supports(Status::class));
    }

    public function testSupportsIntBackedEnum(): void
    {
        self::assertTrue($this->caster->supports(Priority::class));
    }

    public function testDoesNotSupportUnitEnum(): void
    {
        self::assertFalse($this->caster->supports(Color::class));
    }

    public function testDoesNotSupportNonEnumClass(): void
    {
        self::assertFalse($this->caster->supports(\stdClass::class));
    }

    public function testDoesNotSupportScalarType(): void
    {
        self::assertFalse($this->caster->supports('string'));
    }

    public function testCastStringToStringBackedEnum(): void
    {
        $result = $this->caster->cast('active', Status::class);

        self::assertSame(Status::Active, $result);
    }

    public function testCastStringToStringBackedEnumOtherValue(): void
    {
        $result = $this->caster->cast('inactive', Status::class);

        self::assertSame(Status::Inactive, $result);
    }

    public function testCastIntToIntBackedEnum(): void
    {
        $result = $this->caster->cast(2, Priority::class);

        self::assertSame(Priority::Medium, $result);
    }

    public function testCastReturnsExistingEnumAsIs(): void
    {
        $result = $this->caster->cast(Status::Active, Status::class);

        self::assertSame(Status::Active, $result);
    }

    public function testCastInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);

        $this->caster->cast('unknown', Status::class);
    }

    public function testCastInvalidIntValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);

        $this->caster->cast(999, Priority::class);
    }
}
