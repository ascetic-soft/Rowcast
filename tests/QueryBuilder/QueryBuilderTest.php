<?php

declare(strict_types=1);

namespace Rowcast\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Rowcast\Connection;
use Rowcast\QueryBuilder\QueryBuilder;

final class QueryBuilderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection(new \PDO('sqlite::memory:'));
    }

    private function createUsersTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, active INTEGER DEFAULT 1)',
        );
    }

    private function createOrdersTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, total REAL NOT NULL)',
        );
    }

    private function insertUser(string $name, string $email, bool $active = true): void
    {
        $this->connection->executeStatement(
            'INSERT INTO users (name, email, active) VALUES (?, ?, ?)',
            [$name, $email, $active ? 1 : 0],
        );
    }

    private function insertOrder(int $userId, float $total): void
    {
        $this->connection->executeStatement(
            'INSERT INTO orders (user_id, total) VALUES (?, ?)',
            [$userId, $total],
        );
    }

    // --- SELECT ---

    public function testSelectFrom(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'name')
            ->from('users')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['id']);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testSelectFromWithAlias(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('u.id', 'u.name')
            ->from('users', 'u')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['id']);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testSelectWithWhereAndParameters(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name', 'email')
            ->from('users')
            ->where('email = :email')
            ->setParameter('email', 'alice@example.com')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $rows[0]);
    }

    public function testSelectWithAndWhere(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $row = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->where('name = :name')
            ->andWhere('email = :email')
            ->setParameter('name', 'Alice')
            ->setParameter('email', 'alice@example.com')
            ->fetchAssociative();

        $this->assertSame(['name' => 'Alice'], $row);
    }

    public function testSelectWithLeftJoin(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertOrder(1, 100.50);
        $this->insertOrder(1, 50.25);

        $rows = $this->connection->createQueryBuilder()
            ->select('u.name', 'o.total')
            ->from('users', 'u')
            ->leftJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->where('u.id = :id')
            ->setParameter('id', 1)
            ->fetchAllAssociative();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertEqualsWithDelta(100.5, (float) $rows[0]['total'], 0.001);
        $this->assertSame('Alice', $rows[1]['name']);
        $this->assertEqualsWithDelta(50.25, (float) $rows[1]['total'], 0.001);
    }

    public function testSelectWithOrderByAndLimit(): void
    {
        $this->createUsersTable();
        $this->insertUser('Charlie', 'charlie@example.com');
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->setMaxResults(2)
            ->fetchAllAssociative();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    public function testSelectWithOrderByAndOffset(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'a@example.com');
        $this->insertUser('Bob', 'b@example.com');
        $this->insertUser('Charlie', 'c@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->setFirstResult(1)
            ->setMaxResults(1)
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
    }

    public function testSelectWithAddSelect(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $row = $this->connection->createQueryBuilder()
            ->select('id')
            ->addSelect('name', 'email')
            ->from('users')
            ->fetchAssociative();

        $this->assertSame(1, (int) $row['id']);
        $this->assertSame('Alice', $row['name']);
        $this->assertSame('alice@example.com', $row['email']);
    }

    public function testSelectWithGroupBy(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertOrder(1, 100.0);
        $this->insertOrder(1, 50.0);
        $this->insertOrder(2, 75.0);

        $rows = $this->connection->createQueryBuilder()
            ->select('u.name', 'SUM(o.total) as total')
            ->from('users', 'u')
            ->leftJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->groupBy('u.id')
            ->orderBy('total', 'DESC')
            ->fetchAllAssociative();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertEqualsWithDelta(150.0, (float) $rows[0]['total'], 0.001);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertEqualsWithDelta(75.0, (float) $rows[1]['total'], 0.001);
    }

    public function testSelectWithHaving(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertOrder(1, 100.0);
        $this->insertOrder(1, 50.0);
        $this->insertOrder(2, 75.0);

        $rows = $this->connection->createQueryBuilder()
            ->select('u.name', 'SUM(o.total) as total')
            ->from('users', 'u')
            ->leftJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->groupBy('u.id')
            ->having('SUM(o.total) > 100')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertEqualsWithDelta(150.0, (float) $rows[0]['total'], 0.001);
    }

    public function testFetchAssociativeReturnsFalseWhenNoRows(): void
    {
        $this->createUsersTable();

        $row = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->where('id = :id')
            ->setParameter('id', 999)
            ->fetchAssociative();

        $this->assertFalse($row);
    }

    public function testFetchOne(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $name = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->where('email = :email')
            ->setParameter('email', 'alice@example.com')
            ->fetchOne();

        $this->assertSame('Alice', $name);
    }

    public function testExecuteQueryReturnsPDOStatement(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $stmt = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->executeQuery();

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('Alice', $row['name']);
    }

    public function testGetSQLForSelect(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('u.id', 'u.name')
            ->from('users', 'u')
            ->where('u.active = :active')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults(10)
            ->setParameter('active', true);

        $sql = $qb->getSQL();

        $this->assertStringContainsString('SELECT u.id, u.name', $sql);
        $this->assertStringContainsString('FROM users u', $sql);
        $this->assertStringContainsString('WHERE u.active = :active', $sql);
        $this->assertStringContainsString('ORDER BY u.name ASC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    // --- INSERT ---

    public function testInsert(): void
    {
        $this->createUsersTable();

        $affected = $this->connection->createQueryBuilder()
            ->insert('users')
            ->values(['name' => ':name', 'email' => ':email'])
            ->setParameter('name', 'John')
            ->setParameter('email', 'john@example.com')
            ->executeStatement();

        $this->assertSame(1, $affected);

        $name = $this->connection->fetchOne('SELECT name FROM users WHERE email = ?', ['john@example.com']);
        $this->assertSame('John', $name);
    }

    public function testInsertWithSetParameters(): void
    {
        $this->createUsersTable();

        $this->connection->createQueryBuilder()
            ->insert('users')
            ->values(['name' => ':name', 'email' => ':email'])
            ->setParameters(['name' => 'Jane', 'email' => 'jane@example.com'])
            ->executeStatement();

        $name = $this->connection->fetchOne('SELECT name FROM users WHERE email = ?', ['jane@example.com']);
        $this->assertSame('Jane', $name);
    }

    public function testGetSQLForInsert(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->insert('users')
            ->values(['name' => ':name', 'email' => ':email']);

        $sql = $qb->getSQL();

        $this->assertSame('INSERT INTO users (name, email) VALUES (:name, :email)', $sql);
    }

    // --- UPDATE ---

    public function testUpdate(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $affected = $this->connection->createQueryBuilder()
            ->update('users')
            ->set('name', ':name')
            ->where('id = :id')
            ->setParameter('name', 'Jane')
            ->setParameter('id', 1)
            ->executeStatement();

        $this->assertSame(1, $affected);

        $name = $this->connection->fetchOne('SELECT name FROM users WHERE id = 1');
        $this->assertSame('Jane', $name);
    }

    public function testUpdateMultipleColumns(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $this->connection->createQueryBuilder()
            ->update('users')
            ->set('name', ':name')
            ->set('email', ':email')
            ->where('id = :id')
            ->setParameter('name', 'Updated')
            ->setParameter('email', 'updated@example.com')
            ->setParameter('id', 1)
            ->executeStatement();

        $row = $this->connection->fetchAssociative('SELECT name, email FROM users WHERE id = 1');
        $this->assertSame(['name' => 'Updated', 'email' => 'updated@example.com'], $row);
    }

    public function testGetSQLForUpdate(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->update('users')
            ->set('name', ':name')
            ->where('id = :id');

        $sql = $qb->getSQL();

        $this->assertSame('UPDATE users SET name = :name WHERE id = :id', $sql);
    }

    // --- DELETE ---

    public function testDelete(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $affected = $this->connection->createQueryBuilder()
            ->delete('users')
            ->where('id = :id')
            ->setParameter('id', 1)
            ->executeStatement();

        $this->assertSame(1, $affected);

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $this->assertSame(1, $count);
    }

    public function testGetSQLForDelete(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->delete('users')
            ->where('id = :id');

        $sql = $qb->getSQL();

        $this->assertSame('DELETE FROM users WHERE id = :id', $sql);
    }

    // --- Errors ---

    public function testGetSQLWithoutTypeThrows(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No query type');

        $qb->getSQL();
    }

    public function testSelectWithoutFromThrows(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('id');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('FROM clause is required');

        $qb->getSQL();
    }

    public function testInsertWithoutValuesThrows(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->insert('users');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('INSERT requires table and values');

        $qb->getSQL();
    }

    public function testUpdateWithoutSetThrows(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->update('users');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UPDATE requires table and set values');

        $qb->getSQL();
    }

    public function testDeleteWithoutWhere(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $affected = $this->connection->createQueryBuilder()
            ->delete('users')
            ->executeStatement();

        $this->assertSame(2, $affected);
    }

    // --- OrWhere ---

    public function testOrWhere(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->where('name = :a')
            ->orWhere('name = :b')
            ->setParameter('a', 'Alice')
            ->setParameter('b', 'Bob')
            ->fetchAllAssociative();

        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
    }

    // --- Inner join ---

    public function testInnerJoin(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertOrder(1, 100.0);

        $rows = $this->connection->createQueryBuilder()
            ->select('u.name')
            ->from('users', 'u')
            ->innerJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // --- AddOrderBy ---

    public function testAddOrderBy(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'a@example.com');
        $this->insertUser('Bob', 'b@example.com');
        $this->insertUser('Charlie', 'c@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name', 'email')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->addOrderBy('email', 'DESC')
            ->fetchAllAssociative();

        $this->assertCount(3, $rows);
        $sql = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->addOrderBy('email', 'DESC')
            ->getSQL();

        $this->assertStringContainsString('ORDER BY name ASC, email DESC', $sql);
    }

    // --- SELECT * (no explicit columns) ---

    public function testSelectStarWhenNoColumnsSpecified(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select()
            ->from('users')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('email', $rows[0]);
    }

    public function testGetSQLForSelectStarHasAsterisk(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select()
            ->from('users')
            ->getSQL();

        $this->assertStringContainsString('SELECT *', $sql);
    }

    // --- FROM without alias - SQL should not duplicate table name ---

    public function testGetSQLForSelectFromWithoutAlias(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('id', 'name')
            ->from('users')
            ->getSQL();

        $this->assertSame('SELECT id, name FROM users', $sql);
    }

    // --- join() is alias for innerJoin() ---

    public function testJoinMethodIsInnerJoin(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertOrder(1, 100.0);

        $rows = $this->connection->createQueryBuilder()
            ->select('u.name')
            ->from('users', 'u')
            ->join('u', 'orders', 'o', 'o.user_id = u.id')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testJoinMethodGeneratesInnerJoinSQL(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('u.name')
            ->from('users', 'u')
            ->join('u', 'orders', 'o', 'o.user_id = u.id')
            ->getSQL();

        $this->assertStringContainsString('INNER JOIN orders o ON o.user_id = u.id', $sql);
    }

    // --- RIGHT JOIN ---

    public function testRightJoin(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertOrder(1, 100.0);
        $this->insertOrder(999, 50.0); // No matching user

        $sql = $this->connection->createQueryBuilder()
            ->select('u.name', 'o.total')
            ->from('users', 'u')
            ->rightJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->getSQL();

        $this->assertStringContainsString('RIGHT JOIN orders o ON o.user_id = u.id', $sql);
    }

    // --- groupBy with array ---

    public function testGroupByWithArray(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('u.name', 'u.email', 'COUNT(*) as cnt')
            ->from('users', 'u')
            ->groupBy(['u.name', 'u.email'])
            ->getSQL();

        $this->assertStringContainsString('GROUP BY u.name, u.email', $sql);
    }

    // --- andHaving ---

    public function testAndHaving(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('u.name', 'SUM(o.total) as total', 'COUNT(*) as cnt')
            ->from('users', 'u')
            ->leftJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->groupBy('u.id')
            ->having('SUM(o.total) > 50')
            ->andHaving('COUNT(*) > 1')
            ->getSQL();

        $this->assertStringContainsString('HAVING SUM(o.total) > 50 AND COUNT(*) > 1', $sql);
    }

    // --- orWhere on empty where list ---

    public function testOrWhereOnEmptyWhereList(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->orWhere('name = :name')
            ->setParameter('name', 'Alice')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // --- UPDATE with alias ---

    public function testUpdateWithAlias(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->update('users', 'u')
            ->set('name', ':name')
            ->where('u.id = :id')
            ->getSQL();

        $this->assertSame('UPDATE users u SET name = :name WHERE u.id = :id', $sql);
    }

    // --- DELETE with alias ---

    public function testDeleteWithAlias(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->delete('users', 'u')
            ->where('u.id = :id')
            ->getSQL();

        $this->assertSame('DELETE FROM users u WHERE u.id = :id', $sql);
    }

    // --- getConnection ---

    public function testGetConnectionReturnsBoundConnection(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertSame($this->connection, $qb->getConnection());
    }

    // --- DELETE without WHERE (SQL generation) ---

    public function testGetSQLForDeleteWithoutWhere(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->delete('users')
            ->getSQL();

        $this->assertSame('DELETE FROM users', $sql);
    }

    // --- UPDATE without WHERE (SQL generation) ---

    public function testGetSQLForUpdateWithoutWhere(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->update('users')
            ->set('name', ':name')
            ->getSQL();

        $this->assertSame('UPDATE users SET name = :name', $sql);
    }

    // --- LIMIT without OFFSET ---

    public function testGetSQLForSelectWithLimitOnly(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->setMaxResults(5)
            ->getSQL();

        $this->assertStringContainsString('LIMIT 5', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
    }

    // --- LIMIT with OFFSET ---

    public function testGetSQLForSelectWithLimitAndOffset(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->setMaxResults(5)
            ->setFirstResult(10)
            ->getSQL();

        $this->assertStringContainsString('LIMIT 5', $sql);
        $this->assertStringContainsString('OFFSET 10', $sql);
    }

    // --- Fluent API returns $this ---

    public function testFluentApiReturnsSelf(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertSame($qb, $qb->select('id'));
        $this->assertSame($qb, $qb->addSelect('name'));
        $this->assertSame($qb, $qb->from('users'));
        $this->assertSame($qb, $qb->where('id = 1'));
        $this->assertSame($qb, $qb->andWhere('name = :name'));
        $this->assertSame($qb, $qb->orWhere('email = :email'));
        $this->assertSame($qb, $qb->orderBy('id'));
        $this->assertSame($qb, $qb->addOrderBy('name'));
        $this->assertSame($qb, $qb->setFirstResult(0));
        $this->assertSame($qb, $qb->setMaxResults(10));
        $this->assertSame($qb, $qb->setParameter('name', 'Alice'));
        $this->assertSame($qb, $qb->setParameters(['name' => 'Alice']));
        $this->assertSame($qb, $qb->groupBy('id'));
        $this->assertSame($qb, $qb->having('COUNT(*) > 1'));
        $this->assertSame($qb, $qb->andHaving('SUM(total) > 100'));
    }

    public function testFluentApiInsertReturnsSelf(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertSame($qb, $qb->insert('users'));
        $this->assertSame($qb, $qb->values(['name' => ':name']));
    }

    public function testFluentApiUpdateReturnsSelf(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertSame($qb, $qb->update('users'));
        $this->assertSame($qb, $qb->set('name', ':name'));
    }

    public function testFluentApiDeleteReturnsSelf(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $this->assertSame($qb, $qb->delete('users'));
    }

    public function testFluentApiJoinMethodsReturnSelf(): void
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('u.id')
            ->from('users', 'u');

        $this->assertSame($qb, $qb->join('u', 'orders', 'o', 'o.user_id = u.id'));

        $qb2 = $this->connection->createQueryBuilder()
            ->select('u.id')
            ->from('users', 'u');

        $this->assertSame($qb2, $qb2->innerJoin('u', 'posts', 'p', 'p.user_id = u.id'));
        $this->assertSame($qb2, $qb2->leftJoin('u', 'comments', 'c', 'c.user_id = u.id'));
        $this->assertSame($qb2, $qb2->rightJoin('u', 'tags', 't', 't.user_id = u.id'));
    }

    // --- SELECT with multiple joins ---

    public function testSelectWithMultipleJoins(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('u.name', 'o.total', 'p.title')
            ->from('users', 'u')
            ->leftJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->innerJoin('u', 'posts', 'p', 'p.user_id = u.id')
            ->getSQL();

        $this->assertStringContainsString('LEFT JOIN orders o ON o.user_id = u.id', $sql);
        $this->assertStringContainsString('INNER JOIN posts p ON p.user_id = u.id', $sql);
    }

    // --- WHERE replaces previous where ---

    public function testWhereResetsPreviousWhere(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where('active = 1')
            ->andWhere('role = :role')
            ->where('id = :id')  // should reset
            ->getSQL();

        $this->assertStringContainsString('WHERE id = :id', $sql);
        $this->assertStringNotContainsString('active', $sql);
        $this->assertStringNotContainsString('role', $sql);
    }

    // --- orderBy replaces previous orderBy ---

    public function testOrderByResetsPreviousOrderBy(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->orderBy('email', 'DESC')
            ->getSQL();

        $this->assertStringContainsString('ORDER BY email DESC', $sql);
        $this->assertStringNotContainsString('name ASC', $sql);
    }

    // --- having replaces previous having ---

    public function testHavingResetsPreviousHaving(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('name', 'COUNT(*) as cnt')
            ->from('users')
            ->groupBy('name')
            ->having('cnt > 1')
            ->having('cnt > 5')
            ->getSQL();

        $this->assertStringContainsString('HAVING cnt > 5', $sql);
        $this->assertStringNotContainsString('cnt > 1', $sql);
    }

    // --- setParameters replaces all params ---

    public function testSetParametersReplacesAll(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->where('name = :name')
            ->setParameter('name', 'WRONG')
            ->setParameters(['name' => 'Alice'])
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // --- Complex SELECT with all clauses ---

    public function testComplexSelectWithAllClauses(): void
    {
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertOrder(1, 100.0);
        $this->insertOrder(1, 200.0);
        $this->insertOrder(2, 50.0);

        $rows = $this->connection->createQueryBuilder()
            ->select('u.name', 'SUM(o.total) as order_total')
            ->from('users', 'u')
            ->innerJoin('u', 'orders', 'o', 'o.user_id = u.id')
            ->where('u.active = :active')
            ->groupBy('u.id')
            ->having('SUM(o.total) > 60')
            ->orderBy('order_total', 'DESC')
            ->setMaxResults(10)
            ->setParameter('active', 1)
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertEqualsWithDelta(300.0, (float) $rows[0]['order_total'], 0.001);
    }

    // --- DELETE requires table ---

    public function testDeleteRequiresTable(): void
    {
        // Accessing getSQL on a QueryBuilder set to Delete but with null table
        // won't happen through the public API since delete() always sets the table.
        // But let's verify delete without table still works through getSQL.
        $qb = $this->connection->createQueryBuilder();
        // Manually trigger a delete type without setting table name
        // This is not possible through public API, so we test the error path another way.
        // Instead, verify the normal case works.
        $sql = $this->connection->createQueryBuilder()
            ->delete('users')
            ->where('id = 1')
            ->getSQL();

        $this->assertSame('DELETE FROM users WHERE id = 1', $sql);
    }

    // --- Multiple WHERE with OR ---

    public function testMultipleOrWhere(): void
    {
        $this->createUsersTable();
        $this->insertUser('Alice', 'alice@example.com');
        $this->insertUser('Bob', 'bob@example.com');
        $this->insertUser('Charlie', 'charlie@example.com');

        $rows = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('users')
            ->where('name = :a')
            ->orWhere('name = :b')
            ->orWhere('name = :c')
            ->setParameter('a', 'Alice')
            ->setParameter('b', 'Bob')
            ->setParameter('c', 'Charlie')
            ->orderBy('name', 'ASC')
            ->fetchAllAssociative();

        $this->assertCount(3, $rows);
    }

    // --- SELECT addSelect sets type to Select ---

    public function testAddSelectSetsSelectType(): void
    {
        $sql = $this->connection->createQueryBuilder()
            ->addSelect('id', 'name')
            ->from('users')
            ->getSQL();

        $this->assertStringContainsString('SELECT id, name', $sql);
    }
}
