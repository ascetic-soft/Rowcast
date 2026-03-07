<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\NameConverter;

use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use PHPUnit\Framework\TestCase;

final class SnakeCaseToCamelCaseTest extends TestCase
{
    public function testToPropertyNameConvertsSnakeCaseToCamelCase(): void
    {
        $converter = new SnakeCaseToCamelCase();

        self::assertSame('createdAt', $converter->toPropertyName('created_at'));
    }

    public function testToColumnNameConvertsCamelCaseToSnakeCase(): void
    {
        $converter = new SnakeCaseToCamelCase();

        self::assertSame('is_active', $converter->toColumnName('isActive'));
    }
}
