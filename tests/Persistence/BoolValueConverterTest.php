<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Persistence;

use AsceticSoft\Rowcast\Persistence\BoolValueConverter;
use PHPUnit\Framework\TestCase;

final class BoolValueConverterTest extends TestCase
{
    private BoolValueConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new BoolValueConverter();
    }

    public function testSupportsTrue(): void
    {
        self::assertTrue($this->converter->supports(true));
    }

    public function testSupportsFalse(): void
    {
        self::assertTrue($this->converter->supports(false));
    }

    public function testDoesNotSupportString(): void
    {
        self::assertFalse($this->converter->supports('true'));
    }

    public function testDoesNotSupportInt(): void
    {
        self::assertFalse($this->converter->supports(1));
    }

    public function testDoesNotSupportNull(): void
    {
        self::assertFalse($this->converter->supports(null));
    }

    public function testConvertTrueToOne(): void
    {
        self::assertSame(1, $this->converter->convertForDb(true));
    }

    public function testConvertFalseToZero(): void
    {
        self::assertSame(0, $this->converter->convertForDb(false));
    }
}
