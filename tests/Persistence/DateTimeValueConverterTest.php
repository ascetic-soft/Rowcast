<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Persistence;

use AsceticSoft\Rowcast\Persistence\DateTimeValueConverter;
use PHPUnit\Framework\TestCase;

final class DateTimeValueConverterTest extends TestCase
{
    private DateTimeValueConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new DateTimeValueConverter();
    }

    public function testSupportsDateTimeImmutable(): void
    {
        self::assertTrue($this->converter->supports(new \DateTimeImmutable()));
    }

    public function testSupportsDateTime(): void
    {
        self::assertTrue($this->converter->supports(new \DateTime()));
    }

    public function testDoesNotSupportString(): void
    {
        self::assertFalse($this->converter->supports('2025-01-01'));
    }

    public function testDoesNotSupportNull(): void
    {
        self::assertFalse($this->converter->supports(null));
    }

    public function testConvertDateTimeImmutable(): void
    {
        $dt = new \DateTimeImmutable('2025-06-15 10:30:00');

        self::assertSame('2025-06-15 10:30:00', $this->converter->convertForDb($dt));
    }

    public function testConvertDateTime(): void
    {
        $dt = new \DateTime('2025-01-01 00:00:00');

        self::assertSame('2025-01-01 00:00:00', $this->converter->convertForDb($dt));
    }

    public function testCustomFormat(): void
    {
        $converter = new DateTimeValueConverter('Y-m-d');
        $dt = new \DateTimeImmutable('2025-06-15 10:30:00');

        self::assertSame('2025-06-15', $converter->convertForDb($dt));
    }
}
