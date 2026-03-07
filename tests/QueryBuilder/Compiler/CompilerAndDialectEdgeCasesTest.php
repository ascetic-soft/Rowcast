<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Compiler\DeleteCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\InsertCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\SelectCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\SqlFragments;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpdateCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Compiler\UpsertCompiler;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectFactory;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\GenericDialect;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\MysqlDialect;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\PostgresDialect;
use AsceticSoft\Rowcast\QueryBuilder\Dialect\SqliteDialect;
use PHPUnit\Framework\TestCase;

final class CompilerAndDialectEdgeCasesTest extends TestCase
{
    public function testSqlFragmentsHelpers(): void
    {
        self::assertSame(
            'INSERT INTO users (id, email) VALUES (:id, :email)',
            SqlFragments::buildInsertSql('users', ['id' => ':id', 'email' => ':email']),
        );
        self::assertNull(SqlFragments::compileWhere([]));
        self::assertSame('WHERE a = 1 AND b = 2', SqlFragments::compileWhere(['a = 1', 'b = 2']));
    }

    public function testCompilersThrowForInvalidInput(): void
    {
        try {
            new InsertCompiler(null, ['id' => ':id'])->compile();
            self::fail('Expected insert compiler to throw.');
        } catch (\LogicException) {
        }

        try {
            new UpdateCompiler(null, ['name' => ':name'], [])->compile();
            self::fail('Expected update compiler to throw.');
        } catch (\LogicException) {
        }

        try {
            new DeleteCompiler(null, [])->compile();
            self::fail('Expected delete compiler to throw.');
        } catch (\LogicException) {
        }

        try {
            new SelectCompiler([], null, [], [], [], [], [], null, null, new SqliteDialect())->compile();
            self::fail('Expected select compiler to throw.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        new UpsertCompiler(null, ['id' => ':id'], ['id'], ['id'], new SqliteDialect())->compile();
    }

    public function testDialectFactoryAndDialectBehaviors(): void
    {
        self::assertInstanceOf(MysqlDialect::class, DialectFactory::fromDriverName('mysql'));
        self::assertInstanceOf(PostgresDialect::class, DialectFactory::fromDriverName('pgsql'));
        self::assertInstanceOf(SqliteDialect::class, DialectFactory::fromDriverName('sqlite'));
        self::assertInstanceOf(GenericDialect::class, DialectFactory::fromDriverName('oci'));

        $mysql = new MysqlDialect();
        self::assertSame('SELECT 1', $mysql->applyLimitOffset('SELECT 1', null, null));
        self::assertSame('SELECT 1 LIMIT 10 OFFSET 5', $mysql->applyLimitOffset('SELECT 1', 10, 5));
        self::assertSame('', $mysql->compileUpsertClause(['id'], []));

        $sqlite = new SqliteDialect();
        self::assertSame(
            ' ON CONFLICT (id) DO NOTHING',
            $sqlite->compileUpsertClause(['id'], []),
        );

        $postgres = new PostgresDialect();
        self::assertSame(
            ' ON CONFLICT (id) DO NOTHING',
            $postgres->compileUpsertClause(['id'], []),
        );

        try {
            $sqlite->compileUpsertClause([], ['name']);
            self::fail('Expected sqlite upsert to require conflict columns.');
        } catch (\LogicException) {
        }

        try {
            $postgres->compileUpsertClause([], ['name']);
            self::fail('Expected postgres upsert to require conflict columns.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        new GenericDialect('oci')->compileUpsertClause(['id'], ['name']);
    }
}
