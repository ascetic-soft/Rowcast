<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Persistence;

use AsceticSoft\Rowcast\Persistence\BoolValueConverter;
use AsceticSoft\Rowcast\Persistence\DateTimeValueConverter;
use AsceticSoft\Rowcast\Persistence\EnumValueConverter;
use AsceticSoft\Rowcast\Persistence\ValueConverterRegistry;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserStatus;
use PHPUnit\Framework\TestCase;

final class ValueConverterRegistryTest extends TestCase
{
    private ValueConverterRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = ValueConverterRegistry::createDefault();
    }

    public function testCreateDefaultIncludesBoolConverter(): void
    {
        self::assertTrue($this->registry->supports(true));
    }

    public function testCreateDefaultIncludesEnumConverter(): void
    {
        self::assertTrue($this->registry->supports(UserStatus::Active));
    }

    public function testCreateDefaultIncludesDateTimeConverter(): void
    {
        self::assertTrue($this->registry->supports(new \DateTimeImmutable()));
    }

    public function testDoesNotSupportPlainString(): void
    {
        self::assertFalse($this->registry->supports('hello'));
    }

    public function testNullPassesThrough(): void
    {
        self::assertNull($this->registry->convertForDb(null));
    }

    public function testConvertBool(): void
    {
        self::assertSame(1, $this->registry->convertForDb(true));
        self::assertSame(0, $this->registry->convertForDb(false));
    }

    public function testConvertEnum(): void
    {
        self::assertSame('active', $this->registry->convertForDb(UserStatus::Active));
    }

    public function testConvertDateTime(): void
    {
        $dt = new \DateTimeImmutable('2025-01-01 12:00:00');

        self::assertSame('2025-01-01 12:00:00', $this->registry->convertForDb($dt));
    }

    public function testUnsupportedValuePassesThrough(): void
    {
        self::assertSame(42, $this->registry->convertForDb(42));
        self::assertSame('hello', $this->registry->convertForDb('hello'));
        self::assertSame(3.14, $this->registry->convertForDb(3.14));
    }

    public function testAddConverter(): void
    {
        $registry = new ValueConverterRegistry();

        self::assertFalse($registry->supports(true));

        $registry->addConverter(new BoolValueConverter());

        self::assertTrue($registry->supports(true));
        self::assertSame(1, $registry->convertForDb(true));
    }

    public function testEmptyRegistryPassesThroughValues(): void
    {
        $registry = new ValueConverterRegistry();

        self::assertSame('hello', $registry->convertForDb('hello'));
    }
}
