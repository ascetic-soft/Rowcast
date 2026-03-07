<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\TypeConverter;

use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;
use PHPUnit\Framework\TestCase;

final class TypeConverterRegistryTest extends TestCase
{
    public function testToPhpCastsScalarsAndNullable(): void
    {
        $registry = TypeConverterRegistry::defaults();

        self::assertSame(42, $registry->toPhp('42', 'int'));
        self::assertSame(3.14, $registry->toPhp('3.14', 'float'));
        self::assertSame('77', $registry->toPhp(77, 'string'));
        self::assertNull($registry->toPhp(null, '?int'));
    }

    public function testToPhpCastsDateTimeJsonAndEnum(): void
    {
        $registry = TypeConverterRegistry::defaults();

        $date = $registry->toPhp('2026-03-07 12:00:00+00:00', \DateTimeImmutable::class);
        self::assertInstanceOf(\DateTimeImmutable::class, $date);

        $array = $registry->toPhp('["a","b"]', 'array');
        self::assertSame(['a', 'b'], $array);

        $enum = $registry->toPhp('active', UserStatus::class);
        self::assertSame(UserStatus::Active, $enum);
    }

    public function testToDbConvertsBoolDateTimeJsonAndEnum(): void
    {
        $registry = TypeConverterRegistry::defaults();

        self::assertSame(1, $registry->toDb(true));
        self::assertSame('{"k":"v"}', $registry->toDb(['k' => 'v']));
        self::assertSame('inactive', $registry->toDb(UserStatus::Inactive));

        $date = new \DateTimeImmutable('2026-03-07 12:00:00+03:00');
        self::assertSame('2026-03-07 09:00:00+00:00', $registry->toDb($date));
    }
}
