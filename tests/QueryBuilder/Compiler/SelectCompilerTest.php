<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\SelectCompiler;
use PHPUnit\Framework\TestCase;

final class SelectCompilerTest extends TestCase
{
    public function testBasicSelect(): void
    {
        $compiler = new SelectCompiler(
            select: ['id', 'name'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT id, name FROM users', $compiler->compile());
    }

    public function testSelectStar(): void
    {
        $compiler = new SelectCompiler(
            select: [],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT * FROM users', $compiler->compile());
    }

    public function testSelectWithAlias(): void
    {
        $compiler = new SelectCompiler(
            select: ['u.id'],
            from: ['users', 'u'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT u.id FROM users u', $compiler->compile());
    }

    public function testSelectWithWhere(): void
    {
        $compiler = new SelectCompiler(
            select: ['name'],
            from: ['users', 'users'],
            join: [],
            where: ['id = :id'],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT name FROM users WHERE id = :id', $compiler->compile());
    }

    public function testSelectWithMultipleWhere(): void
    {
        $compiler = new SelectCompiler(
            select: ['name'],
            from: ['users', 'users'],
            join: [],
            where: ['id = :id', 'active = 1'],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT name FROM users WHERE id = :id AND active = 1', $compiler->compile());
    }

    public function testSelectWithJoin(): void
    {
        $compiler = new SelectCompiler(
            select: ['u.name', 'o.total'],
            from: ['users', 'u'],
            join: [['LEFT', 'u', 'orders', 'o', 'o.user_id = u.id']],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT u.name, o.total FROM users u LEFT JOIN orders o ON o.user_id = u.id', $compiler->compile());
    }

    public function testSelectWithGroupByAndHaving(): void
    {
        $compiler = new SelectCompiler(
            select: ['name', 'COUNT(*) as cnt'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: ['name'],
            having: ['cnt > 1'],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT name, COUNT(*) as cnt FROM users GROUP BY name HAVING cnt > 1', $compiler->compile());
    }

    public function testSelectWithOrderBy(): void
    {
        $compiler = new SelectCompiler(
            select: ['name'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: ['name ASC'],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT name FROM users ORDER BY name ASC', $compiler->compile());
    }

    public function testSelectWithLimit(): void
    {
        $compiler = new SelectCompiler(
            select: ['id'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: 10,
            firstResult: null,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT id FROM users LIMIT 10', $compiler->compile());
    }

    public function testSelectWithLimitAndOffset(): void
    {
        $compiler = new SelectCompiler(
            select: ['id'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: 10,
            firstResult: 5,
            driverName: 'sqlite',
        );

        self::assertSame('SELECT id FROM users LIMIT 10 OFFSET 5', $compiler->compile());
    }

    public function testSelectWithLimitMysql(): void
    {
        $compiler = new SelectCompiler(
            select: ['id'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: 5,
            firstResult: null,
            driverName: 'mysql',
        );

        self::assertStringContainsString('LIMIT 5', $compiler->compile());
    }

    public function testSelectWithLimitPgsql(): void
    {
        $compiler = new SelectCompiler(
            select: ['id'],
            from: ['users', 'users'],
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: 5,
            firstResult: null,
            driverName: 'pgsql',
        );

        self::assertStringContainsString('LIMIT 5', $compiler->compile());
    }

    public function testThrowsWithoutFrom(): void
    {
        $compiler = new SelectCompiler(
            select: ['id'],
            from: null,
            join: [],
            where: [],
            groupBy: [],
            having: [],
            orderBy: [],
            maxResults: null,
            firstResult: null,
            driverName: 'sqlite',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('FROM clause is required');

        $compiler->compile();
    }
}
