<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\TypeCaster;

use DateTimeImmutable;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use AsceticSoft\Rowcast\TypeCaster\DateTimeTypeCaster;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface;

final class DateTimeTypeCasterTest extends TestCase
{
    private DateTimeTypeCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new DateTimeTypeCaster();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(TypeCasterInterface::class, $this->caster);
    }

    #[DataProvider('supportedTypesProvider')]
    public function testSupportedTypes(string $type): void
    {
        self::assertTrue($this->caster->supports($type));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedTypesProvider(): iterable
    {
        yield 'DateTimeImmutable' => [DateTimeImmutable::class];
        yield 'DateTime' => [DateTime::class];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testUnsupportedTypes(string $type): void
    {
        self::assertFalse($this->caster->supports($type));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedTypesProvider(): iterable
    {
        yield 'string' => ['string'];
        yield 'int' => ['int'];
        yield 'stdClass' => [\stdClass::class];
        yield 'DateTimeInterface' => [\DateTimeInterface::class];
    }

    public function testCastStringToDateTimeImmutable(): void
    {
        $result = $this->caster->cast('2025-06-15 10:30:00', DateTimeImmutable::class);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2025-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCastStringToDateTime(): void
    {
        $result = $this->caster->cast('2025-06-15 10:30:00', DateTime::class);

        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2025-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCastDateOnlyString(): void
    {
        $result = $this->caster->cast('2025-01-01', DateTimeImmutable::class);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2025-01-01', $result->format('Y-m-d'));
    }

    public function testCastIso8601String(): void
    {
        $result = $this->caster->cast('2025-06-15T10:30:00+03:00', DateTimeImmutable::class);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2025-06-15T10:30:00+03:00', $result->format('c'));
    }

    public function testCastReturnsExistingDateTimeImmutableAsIs(): void
    {
        $original = new DateTimeImmutable('2025-06-15');
        $result = $this->caster->cast($original, DateTimeImmutable::class);

        self::assertSame($original, $result);
    }

    public function testCastReturnsExistingDateTimeAsIs(): void
    {
        $original = new DateTime('2025-06-15');
        $result = $this->caster->cast($original, DateTime::class);

        self::assertSame($original, $result);
    }

    public function testCastInvalidStringThrowsException(): void
    {
        $this->expectException(\DateMalformedStringException::class);

        $this->caster->cast('not-a-date', DateTimeImmutable::class);
    }
}
