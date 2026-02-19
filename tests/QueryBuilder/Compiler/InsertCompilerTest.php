<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\InsertCompiler;
use PHPUnit\Framework\TestCase;

final class InsertCompilerTest extends TestCase
{
    public function testBasicInsert(): void
    {
        $compiler = new InsertCompiler('users', ['name' => ':name', 'email' => ':email']);

        self::assertSame('INSERT INTO users (name, email) VALUES (:name, :email)', $compiler->compile());
    }

    public function testSingleColumnInsert(): void
    {
        $compiler = new InsertCompiler('users', ['name' => ':name']);

        self::assertSame('INSERT INTO users (name) VALUES (:name)', $compiler->compile());
    }

    public function testThrowsWithoutTable(): void
    {
        $compiler = new InsertCompiler(null, ['name' => ':name']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('INSERT requires table and values');

        $compiler->compile();
    }

    public function testThrowsWithoutValues(): void
    {
        $compiler = new InsertCompiler('users', []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('INSERT requires table and values');

        $compiler->compile();
    }
}
