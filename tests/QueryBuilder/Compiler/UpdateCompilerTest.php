<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpdateCompiler;
use PHPUnit\Framework\TestCase;

final class UpdateCompilerTest extends TestCase
{
    public function testBasicUpdate(): void
    {
        $compiler = new UpdateCompiler('users', ['name' => ':name'], ['id = :id']);

        self::assertSame('UPDATE users SET name = :name WHERE id = :id', $compiler->compile());
    }

    public function testUpdateMultipleColumns(): void
    {
        $compiler = new UpdateCompiler('users', ['name' => ':name', 'email' => ':email'], []);

        self::assertSame('UPDATE users SET name = :name, email = :email', $compiler->compile());
    }

    public function testUpdateWithMultipleWhere(): void
    {
        $compiler = new UpdateCompiler('users', ['name' => ':name'], ['id = :id', 'active = 1']);

        self::assertSame('UPDATE users SET name = :name WHERE id = :id AND active = 1', $compiler->compile());
    }

    public function testUpdateWithAlias(): void
    {
        $compiler = new UpdateCompiler('users u', ['name' => ':name'], ['u.id = :id']);

        self::assertSame('UPDATE users u SET name = :name WHERE u.id = :id', $compiler->compile());
    }

    public function testThrowsWithoutTable(): void
    {
        $compiler = new UpdateCompiler(null, ['name' => ':name'], []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UPDATE requires table and set values');

        $compiler->compile();
    }

    public function testThrowsWithoutSetValues(): void
    {
        $compiler = new UpdateCompiler('users', [], []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UPDATE requires table and set values');

        $compiler->compile();
    }
}
