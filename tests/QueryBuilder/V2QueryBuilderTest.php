<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\QueryBuilder;

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\ConnectionInterface;
use AsceticSoft\Rowcast\QueryBuilder\QueryBuilder;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;
use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use PHPUnit\Framework\TestCase;

final class V2QueryBuilderTest extends TestCase
{
    public function testBuildsUpsertSqlForSqlite(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->upsert('cards')
            ->values(['id' => ':id', 'title' => ':title'])
            ->onConflict('id')
            ->doUpdateSet(['title'])
            ->getSQL();

        self::assertSame(
            'INSERT INTO cards (id, title) VALUES (:id, :title) ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title',
            $sql,
        );
    }

    public function testWhereAcceptsArrayAndMapsToParameters(): void
    {
        $connection = $this->createUsersConnection();

        $qb = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['email' => 'a@example.com', 'is_active' => 1]);

        self::assertSame(
            'SELECT id FROM users WHERE email = :w_email AND is_active = :w_is_active',
            $qb->getSQL(),
        );
        self::assertSame(1, $qb->fetchOne());
    }

    public function testWhereAndOrWhereArrayGeneratesUniqueParameterNames(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['id' => 1])
            ->orWhere(['id' => 2])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE (id = :w_id OR id = :w_id_1)', $sql);
    }

    public function testWhereNullGeneratesIsNull(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['deleted_at' => null])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE deleted_at IS NULL', $sql);
    }

    public function testWhereNotEqualNullGeneratesIsNotNull(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['deleted_at !=' => null])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE deleted_at IS NOT NULL', $sql);
    }

    public function testWhereArrayGeneratesIn(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['id' => [1, 2, 3]])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE id IN (:w_id, :w_id_1, :w_id_2)', $sql);
    }

    public function testWhereExplicitInGeneratesIn(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['status IN' => ['active', 'pending']])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE status IN (:w_status, :w_status_1)', $sql);
    }

    public function testWhereNotEqualArrayGeneratesNotIn(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['id !=' => [1, 2]])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE id NOT IN (:w_id, :w_id_1)', $sql);
    }

    public function testWhereEmptyArrayInGeneratesFalse(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['id' => []])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE 1 = 0', $sql);
    }

    public function testWhereComparisonOperators(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where([
                'age >' => 18,
                'age >=' => 19,
                'age <' => 100,
                'age <=' => 99,
                'status !=' => 'banned',
            ])
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE age > :w_age AND age >= :w_age_1 AND age < :w_age_2 AND age <= :w_age_3 AND status != :w_status',
            $sql,
        );
    }

    public function testWhereLike(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['name LIKE' => '%alice%'])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE name LIKE :w_name', $sql);
    }

    public function testWhereIlike(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getDriverName')->willReturn('pgsql');

        $sql = new QueryBuilder($connection)
            ->select('id')
            ->from('users')
            ->where(['name ILIKE' => '%alice%'])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE name ILIKE :w_name', $sql);
    }

    public function testWhereNotIlike(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getDriverName')->willReturn('pgsql');

        $sql = new QueryBuilder($connection)
            ->select('id')
            ->from('users')
            ->where(['name NOT ILIKE' => '%bot%'])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE name NOT ILIKE :w_name', $sql);
    }

    public function testWhereIlikeThrowsForSqliteDialect(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported WHERE operator "ILIKE"');

        $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['name ILIKE' => '%alice%'])
            ->getSQL();
    }

    public function testWhereBetween(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['age BETWEEN' => [18, 65]])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE age BETWEEN :w_age AND :w_age_1', $sql);
    }

    public function testWhereMixedOperators(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where([
                'status' => ['active', 'pending'],
                'deleted_at' => null,
                'age >=' => 18,
                'name NOT LIKE' => '%bot%',
            ])
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE status IN (:w_status, :w_status_1) AND deleted_at IS NULL AND age >= :w_age AND name NOT LIKE :w_name',
            $sql,
        );
    }

    public function testWhereBackedEnumScalarValueIsNormalized(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $qb = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['status' => UserStatus::Active]);

        self::assertSame('SELECT id FROM users WHERE status = :w_status', $qb->getSQL());
        self::assertSame(['w_status' => 'active'], $this->readQueryBuilderParameters($qb));
    }

    public function testWhereBackedEnumArrayValueGeneratesInWithScalarParameters(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $qb = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['status' => [UserStatus::Active, UserStatus::Inactive]]);

        self::assertSame(
            'SELECT id FROM users WHERE status IN (:w_status, :w_status_1)',
            $qb->getSQL(),
        );
        self::assertSame(
            ['w_status' => 'active', 'w_status_1' => 'inactive'],
            $this->readQueryBuilderParameters($qb),
        );
    }

    public function testWhereBoolNormalizesToIntWhenTypeConverterIsSet(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'), false, TypeConverterRegistry::defaults());

        $qb = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['is_active' => true, 'is_verified' => false]);

        self::assertSame(
            ['w_is_active' => 1, 'w_is_verified' => 0],
            $this->readQueryBuilderParameters($qb),
        );
    }

    public function testWhereDateTimeNormalizesToStringWhenTypeConverterIsSet(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'), false, TypeConverterRegistry::defaults());
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00+03:00');

        $qb = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['created_at >=' => $createdAt]);

        self::assertSame(
            ['w_created_at' => '2026-01-01 07:00:00+00:00'],
            $this->readQueryBuilderParameters($qb),
        );
    }

    public function testSetParameterBoolNormalizedAtExecution(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'), false, TypeConverterRegistry::defaults());
        $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, is_active INTEGER NOT NULL)');
        $connection->executeStatement('INSERT INTO users (id, is_active) VALUES (1, 1), (2, 0)');

        $result = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where('is_active = :active')
            ->setParameter('active', false)
            ->fetchOne();

        self::assertSame(2, $result);
    }

    public function testSetParameterDateTimeNormalizedAtExecution(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'), false, TypeConverterRegistry::defaults());
        $connection->executeStatement('CREATE TABLE events (id INTEGER PRIMARY KEY, created_at TEXT NOT NULL)');
        $connection->executeStatement("INSERT INTO events (id, created_at) VALUES (1, '2026-01-01 07:00:00+00:00')");

        $result = $connection->createQueryBuilder()
            ->select('id')
            ->from('events')
            ->where('created_at = :created_at')
            ->setParameter('created_at', new \DateTimeImmutable('2026-01-01 10:00:00+03:00'))
            ->fetchOne();

        self::assertSame(1, $result);
    }

    public function testWhereInIntegrationWithSqlite(): void
    {
        $connection = $this->createUsersConnection();

        $rows = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['id' => [1, 2]])
            ->orderBy('id')
            ->fetchAllAssociative();

        self::assertSame([['id' => 1], ['id' => 2]], $rows);
    }

    public function testWhereOrMethodTwoGroups(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->whereOr(
                ['status' => 'active', 'age >' => 18],
                ['role' => 'admin'],
            )
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE ((status = :w_status AND age > :w_age) OR (role = :w_role))',
            $sql,
        );
    }

    public function testAndWhereOrCombinedWithWhere(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where(['deleted_at' => null])
            ->andWhereOr(['status' => 'active'], ['role' => 'admin'])
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE deleted_at IS NULL AND ((status = :w_status) OR (role = :w_role))',
            $sql,
        );
    }

    public function testWhereOrSingleGroupNoParens(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->whereOr(['status' => 'active'])
            ->getSQL();

        self::assertSame('SELECT id FROM users WHERE status = :w_status', $sql);
    }

    public function testNestedOrKeyInArray(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where([
                'age >' => 18,
                '$or' => [
                    ['status' => 'active'],
                    ['role' => 'admin'],
                ],
            ])
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE age > :w_age AND ((status = :w_status) OR (role = :w_role))',
            $sql,
        );
    }

    public function testNestedAndKeyInsideOr(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where([
                '$or' => [
                    ['status' => 'active'],
                    ['$and' => [
                        ['role' => 'admin'],
                        ['verified' => true],
                    ]],
                ],
            ])
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE ((status = :w_status) OR ((role = :w_role) AND (verified = :w_verified)))',
            $sql,
        );
    }

    public function testOrWithAllOperators(): void
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));

        $sql = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where([
                '$or' => [
                    ['status' => ['active', 'pending'], 'deleted_at' => null],
                    ['name LIKE' => 'A%', 'age BETWEEN' => [18, 65]],
                ],
            ])
            ->getSQL();

        self::assertSame(
            'SELECT id FROM users WHERE ((status IN (:w_status, :w_status_1) AND deleted_at IS NULL) OR (name LIKE :w_name AND age BETWEEN :w_age AND :w_age_1))',
            $sql,
        );
    }

    public function testWhereOrIntegrationWithSqlite(): void
    {
        $connection = $this->createUsersConnection();

        $rows = $connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->whereOr(
                ['status' => 'active'],
                ['id' => 2],
            )
            ->orderBy('id')
            ->fetchAllAssociative();

        self::assertSame([['id' => 1], ['id' => 2]], $rows);
    }

    private function createUsersConnection(): Connection
    {
        $connection = new Connection(new \PDO('sqlite::memory:'));
        $connection->executeStatement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, is_active INTEGER, deleted_at TEXT, age INTEGER, status TEXT, name TEXT)',
        );
        $connection->executeStatement(
            'INSERT INTO users (id, email, is_active, deleted_at, age, status, name) VALUES '
            . "(1, 'a@example.com', 1, NULL, 20, 'active', 'Alice'), "
            . "(2, 'b@example.com', 0, '2026-01-01 00:00:00', 35, 'pending', 'Bob'), "
            . "(3, 'c@example.com', 1, NULL, 17, 'banned', 'BotUser')",
        );

        return $connection;
    }

    /**
     * @return array<string|int, mixed>
     */
    private function readQueryBuilderParameters(QueryBuilder $qb): array
    {
        $reflection = new \ReflectionProperty(QueryBuilder::class, 'parameters');
        $reflection->setAccessible(true);

        /** @var array<string|int, mixed> $parameters */
        $parameters = $reflection->getValue($qb);

        return $parameters;
    }
}
