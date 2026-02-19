<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\DeleteCompiler;
use PHPUnit\Framework\TestCase;

final class DeleteCompilerTest extends TestCase
{
    public function testBasicDelete(): void
    {
        $compiler = new DeleteCompiler('users', ['id = :id']);

        self::assertSame('DELETE FROM users WHERE id = :id', $compiler->compile());
    }

    public function testDeleteWithoutWhere(): void
    {
        $compiler = new DeleteCompiler('users', []);

        self::assertSame('DELETE FROM users', $compiler->compile());
    }

    public function testDeleteWithMultipleWhere(): void
    {
        $compiler = new DeleteCompiler('users', ['id = :id', 'active = 0']);

        self::assertSame('DELETE FROM users WHERE id = :id AND active = 0', $compiler->compile());
    }

    public function testDeleteWithAlias(): void
    {
        $compiler = new DeleteCompiler('users u', ['u.id = :id']);

        self::assertSame('DELETE FROM users u WHERE u.id = :id', $compiler->compile());
    }

    public function testThrowsWithoutTable(): void
    {
        $compiler = new DeleteCompiler(null, []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('DELETE requires table');

        $compiler->compile();
    }
}
