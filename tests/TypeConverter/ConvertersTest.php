<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\TypeConverter;

use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use AsceticSoft\Rowcast\TypeConverter\BoolConverter;
use AsceticSoft\Rowcast\TypeConverter\DateTimeConverter;
use AsceticSoft\Rowcast\TypeConverter\EnumConverter;
use AsceticSoft\Rowcast\TypeConverter\JsonConverter;
use AsceticSoft\Rowcast\TypeConverter\ScalarConverter;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;
use PHPUnit\Framework\TestCase;

final class ConvertersTest extends TestCase
{
    public function testBoolConverterSupportsAndToDbValidation(): void
    {
        $converter = new BoolConverter();

        self::assertTrue($converter->supports('bool'));
        self::assertFalse($converter->supports('int'));
        self::assertTrue($converter->toPhp('1', 'bool'));
        self::assertSame(0, $converter->toDb(false));

        $this->expectException(\InvalidArgumentException::class);
        $converter->toDb(1);
    }

    public function testScalarConverterUnsupportedAndNonScalarInputValidation(): void
    {
        $converter = new ScalarConverter();

        self::assertTrue($converter->supports('int'));
        self::assertFalse($converter->supports('bool'));
        self::assertSame(0, $converter->toPhp(null, 'int'));
        self::assertSame('42', $converter->toPhp(42, 'string'));
        self::assertSame(5, $converter->toDb(5));

        try {
            $converter->toPhp([], 'int');
            self::fail('Expected InvalidArgumentException for non scalar input.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $converter->toPhp('10', 'bool');
    }

    public function testEnumConverterHandlesEnumInstancesAndValidation(): void
    {
        $converter = new EnumConverter();

        self::assertTrue($converter->supports(UserStatus::class));
        self::assertFalse($converter->supports(\stdClass::class));

        $enum = $converter->toPhp('active', UserStatus::class);
        self::assertSame(UserStatus::Active, $enum);
        self::assertSame(UserStatus::Inactive, $converter->toPhp(UserStatus::Inactive, UserStatus::class));
        self::assertSame('active', $converter->toDb(UserStatus::Active));

        try {
            $converter->toPhp([], UserStatus::class);
            self::fail('Expected InvalidArgumentException for non scalar enum value.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $converter->toDb('active');
    }

    public function testJsonConverterCoversDecodedTypeAndInputValidation(): void
    {
        $converter = new JsonConverter();

        self::assertTrue($converter->supports('array'));
        self::assertFalse($converter->supports('string'));
        self::assertSame(['raw' => true], $converter->toPhp(['raw' => true], 'array'));
        self::assertSame([], $converter->toPhp('', 'array'));
        self::assertSame(['a' => 1], $converter->toPhp('{"a":1}', 'array'));
        self::assertSame('{"k":"v"}', $converter->toDb(['k' => 'v']));

        try {
            $converter->toPhp(123, 'array');
            self::fail('Expected InvalidArgumentException for non string and non array JSON source.');
        } catch (\InvalidArgumentException) {
        }

        try {
            $converter->toPhp('"text"', 'array');
            self::fail('Expected InvalidArgumentException for decoded non-array value.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $converter->toDb('nope');
    }

    public function testDateTimeConverterCoversMutableImmutableAndValidation(): void
    {
        $converter = new DateTimeConverter();
        $mutable = new \DateTime('2026-01-01 00:00:00+03:00');
        $immutable = new \DateTimeImmutable('2026-01-01 00:00:00+03:00');

        self::assertTrue($converter->supports(\DateTimeImmutable::class));
        self::assertTrue($converter->supports(\DateTime::class));
        self::assertTrue($converter->supports(\DateTimeInterface::class));

        self::assertInstanceOf(\DateTimeImmutable::class, $converter->toPhp($mutable, \DateTimeInterface::class));
        self::assertSame($mutable, $converter->toPhp($mutable, \DateTime::class));
        self::assertInstanceOf(\DateTime::class, $converter->toPhp($immutable, \DateTime::class));
        self::assertInstanceOf(\DateTimeImmutable::class, $converter->toPhp('2026-01-01 00:00:00+00:00', \DateTimeImmutable::class));
        self::assertSame('2025-12-31 21:00:00+00:00', $converter->toDb($immutable));

        try {
            $converter->toPhp([], \DateTimeImmutable::class);
            self::fail('Expected InvalidArgumentException for non scalar datetime value.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $converter->toDb('2026-01-01');
    }

    public function testTypeConverterRegistrySupportsAndThrowsForUnknownType(): void
    {
        $registry = TypeConverterRegistry::defaults();

        self::assertTrue($registry->supports('int'));
        self::assertFalse($registry->supports('resource'));
        self::assertNull($registry->toDb(null));
        self::assertSame('plain', $registry->toDb('plain'));

        $this->expectException(\InvalidArgumentException::class);
        $registry->toPhp('v', 'resource');
    }
}
