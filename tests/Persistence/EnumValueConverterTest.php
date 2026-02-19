<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Persistence;

use AsceticSoft\Rowcast\Persistence\EnumValueConverter;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserStatus;
use PHPUnit\Framework\TestCase;

final class EnumValueConverterTest extends TestCase
{
    private EnumValueConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new EnumValueConverter();
    }

    public function testSupportsBackedEnum(): void
    {
        self::assertTrue($this->converter->supports(UserStatus::Active));
    }

    public function testDoesNotSupportString(): void
    {
        self::assertFalse($this->converter->supports('active'));
    }

    public function testDoesNotSupportInt(): void
    {
        self::assertFalse($this->converter->supports(42));
    }

    public function testDoesNotSupportNull(): void
    {
        self::assertFalse($this->converter->supports(null));
    }

    public function testConvertStringEnum(): void
    {
        self::assertSame('active', $this->converter->convertForDb(UserStatus::Active));
    }

    public function testConvertAnotherStringEnum(): void
    {
        self::assertSame('banned', $this->converter->convertForDb(UserStatus::Banned));
    }
}
