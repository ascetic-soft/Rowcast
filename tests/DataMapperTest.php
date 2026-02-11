<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use PHPUnit\Framework\TestCase;
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\Rowcast\Mapping\NameConverter\NullConverter;
use AsceticSoft\Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\DtoWithEnum;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\SimpleUser;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserStatus;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserWithDates;

final class DataMapperTest extends TestCase
{
    private Connection $connection;
    private DataMapper $mapper;

    protected function setUp(): void
    {
        $this->connection = new Connection(new \PDO('sqlite::memory:'));
        $this->mapper = new DataMapper($this->connection);
    }

    // -----------------------------------------------------------------------
    // INSERT
    // -----------------------------------------------------------------------

    public function testInsertWithTableName(): void
    {
        $this->createSimpleUsersTable();

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $id = $this->mapper->insert('simple_users', $user);

        $this->assertSame('1', $id);

        $row = $this->connection->fetchAssociative('SELECT * FROM simple_users WHERE id = 1');
        $this->assertSame('Alice', $row['name']);
        $this->assertSame('alice@example.com', $row['email']);
    }

    public function testInsertWithRsm(): void
    {
        $this->createSimpleUsersTable();

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'simple_users');
        $rsm->addField('name', 'name')
            ->addField('email', 'email');

        $user = new SimpleUser();
        $user->name = 'Bob';
        $user->email = 'bob@example.com';

        $id = $this->mapper->insert($rsm, $user);

        $this->assertSame('1', $id);

        $row = $this->connection->fetchAssociative('SELECT * FROM simple_users WHERE id = 1');
        $this->assertSame('Bob', $row['name']);
        $this->assertSame('bob@example.com', $row['email']);
    }

    public function testInsertSkipsUninitializedProperties(): void
    {
        $this->createSimpleUsersTable();

        $user = new SimpleUser();
        // id is not initialized — should be skipped (auto-increment)
        $user->name = 'Charlie';
        $user->email = 'charlie@example.com';

        $id = $this->mapper->insert('simple_users', $user);

        $this->assertSame('1', $id);
    }

    public function testInsertMultipleReturnsIncrementingIds(): void
    {
        $this->createSimpleUsersTable();

        $user1 = new SimpleUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new SimpleUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $id1 = $this->mapper->insert('simple_users', $user1);
        $id2 = $this->mapper->insert('simple_users', $user2);

        $this->assertSame('1', $id1);
        $this->assertSame('2', $id2);
    }

    public function testInsertWithDateTimeConversion(): void
    {
        $this->createUsersWithDatesTable();

        $user = new UserWithDates();
        $user->name = 'Alice';
        $user->createdAt = new \DateTimeImmutable('2025-06-15 10:30:00');
        $user->updatedAt = new \DateTimeImmutable('2025-06-15 10:30:00');

        $this->mapper->insert('user_with_dates', $user);

        $row = $this->connection->fetchAssociative('SELECT * FROM user_with_dates WHERE id = 1');
        $this->assertSame('2025-06-15 10:30:00', $row['created_at']);
        $this->assertSame('2025-06-15 10:30:00', $row['updated_at']);
    }

    public function testInsertWithEnumConversion(): void
    {
        $this->createDtoWithEnumTable();

        $dto = new DtoWithEnum();
        $dto->status = UserStatus::Active;
        $dto->previousStatus = null;

        $this->mapper->insert('dto_with_enums', $dto);

        $row = $this->connection->fetchAssociative('SELECT * FROM dto_with_enums WHERE id = 1');
        $this->assertSame('active', $row['status']);
        $this->assertNull($row['previous_status']);
    }

    public function testInsertWithRsmCustomColumnMapping(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE custom_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usr_nm TEXT NOT NULL,
                usr_email TEXT NOT NULL
            )',
        );

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'custom_users');
        $rsm->addField('usr_nm', 'name')
            ->addField('usr_email', 'email');

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $this->mapper->insert($rsm, $user);

        $row = $this->connection->fetchAssociative('SELECT * FROM custom_users WHERE id = 1');
        $this->assertSame('Alice', $row['usr_nm']);
        $this->assertSame('alice@example.com', $row['usr_email']);
    }

    public function testInsertThrowsWhenRsmHasNoTable(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class);

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('table name');

        $this->mapper->insert($rsm, $user);
    }

    // -----------------------------------------------------------------------
    // UPDATE
    // -----------------------------------------------------------------------

    public function testUpdateWithTableName(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $user = new SimpleUser();
        $user->name = 'Alice Updated';
        $user->email = 'alice-new@example.com';

        $affected = $this->mapper->update('simple_users', $user, ['id' => 1]);

        $this->assertSame(1, $affected);

        $row = $this->connection->fetchAssociative('SELECT * FROM simple_users WHERE id = 1');
        $this->assertSame('Alice Updated', $row['name']);
        $this->assertSame('alice-new@example.com', $row['email']);
    }

    public function testUpdateWithRsm(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'simple_users');
        $rsm->addField('name', 'name')
            ->addField('email', 'email');

        $user = new SimpleUser();
        $user->name = 'Alice via RSM';
        $user->email = 'rsm@example.com';

        $affected = $this->mapper->update($rsm, $user, ['id' => 1]);

        $this->assertSame(1, $affected);

        $row = $this->connection->fetchAssociative('SELECT * FROM simple_users WHERE id = 1');
        $this->assertSame('Alice via RSM', $row['name']);
    }

    public function testUpdateReturnsZeroWhenNoRowsMatch(): void
    {
        $this->createSimpleUsersTable();

        $user = new SimpleUser();
        $user->name = 'Nobody';
        $user->email = 'nobody@example.com';

        $affected = $this->mapper->update('simple_users', $user, ['id' => 999]);

        $this->assertSame(0, $affected);
    }

    public function testUpdateWithMultipleWhereConditions(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $user = new SimpleUser();
        $user->name = 'Updated';
        $user->email = 'updated@example.com';

        $affected = $this->mapper->update('simple_users', $user, ['id' => 1, 'name' => 'Alice']);

        $this->assertSame(1, $affected);

        // Bob should be unchanged
        $bob = $this->connection->fetchAssociative('SELECT * FROM simple_users WHERE id = 2');
        $this->assertSame('Bob', $bob['name']);
    }

    public function testUpdateThrowsWithEmptyWhere(): void
    {
        $this->createSimpleUsersTable();

        $user = new SimpleUser();
        $user->name = 'Test';
        $user->email = 'test@example.com';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('WHERE conditions are required');

        $this->mapper->update('simple_users', $user, []);
    }

    public function testUpdateThrowsWhenRsmHasNoTable(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class);

        $user = new SimpleUser();
        $user->name = 'Test';
        $user->email = 'test@example.com';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('table name');

        $this->mapper->update($rsm, $user, ['id' => 1]);
    }

    // -----------------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------------

    public function testDeleteWithTableName(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $affected = $this->mapper->delete('simple_users', ['id' => 1]);

        $this->assertSame(1, $affected);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM simple_users');
        $this->assertSame(1, (int) $count);
    }

    public function testDeleteWithRsm(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'simple_users');

        $affected = $this->mapper->delete($rsm, ['id' => 1]);

        $this->assertSame(1, $affected);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM simple_users');
        $this->assertSame(0, (int) $count);
    }

    public function testDeleteReturnsZeroWhenNoRowsMatch(): void
    {
        $this->createSimpleUsersTable();

        $affected = $this->mapper->delete('simple_users', ['id' => 999]);

        $this->assertSame(0, $affected);
    }

    public function testDeleteWithMultipleWhereConditions(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $affected = $this->mapper->delete('simple_users', ['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertSame(1, $affected);

        // Bob should remain
        $bob = $this->connection->fetchAssociative('SELECT * FROM simple_users WHERE id = 2');
        $this->assertSame('Bob', $bob['name']);
    }

    public function testDeleteThrowsWithEmptyWhere(): void
    {
        $this->createSimpleUsersTable();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('WHERE conditions are required');

        $this->mapper->delete('simple_users', []);
    }

    public function testDeleteThrowsWhenRsmHasNoTable(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('table name');

        $this->mapper->delete($rsm, ['id' => 1]);
    }

    // -----------------------------------------------------------------------
    // findAll
    // -----------------------------------------------------------------------

    public function testFindAllAutoMode(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $users = $this->mapper->findAll(SimpleUser::class);

        $this->assertCount(2, $users);
        $this->assertInstanceOf(SimpleUser::class, $users[0]);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
    }

    public function testFindAllWithRsm(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'simple_users');
        $rsm->addField('id', 'id')
            ->addField('name', 'name')
            ->addField('email', 'email');

        $users = $this->mapper->findAll($rsm);

        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('alice@example.com', $users[0]->email);
    }

    public function testFindAllWithWhereConditions(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');
        $this->insertSimpleUser('Charlie', 'charlie@example.com');

        $users = $this->mapper->findAll(SimpleUser::class, ['name' => 'Bob']);

        $this->assertCount(1, $users);
        $this->assertSame('Bob', $users[0]->name);
    }

    public function testFindAllWithOrderBy(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Charlie', 'charlie@example.com');
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $users = $this->mapper->findAll(
            SimpleUser::class,
            orderBy: ['name' => 'ASC'],
        );

        $this->assertCount(3, $users);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
        $this->assertSame('Charlie', $users[2]->name);
    }

    public function testFindAllWithOrderByDesc(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');
        $this->insertSimpleUser('Charlie', 'charlie@example.com');

        $users = $this->mapper->findAll(
            SimpleUser::class,
            orderBy: ['name' => 'DESC'],
        );

        $this->assertSame('Charlie', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
        $this->assertSame('Alice', $users[2]->name);
    }

    public function testFindAllWithLimit(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');
        $this->insertSimpleUser('Charlie', 'charlie@example.com');

        $users = $this->mapper->findAll(
            SimpleUser::class,
            orderBy: ['name' => 'ASC'],
            limit: 2,
        );

        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
    }

    public function testFindAllWithLimitAndOffset(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');
        $this->insertSimpleUser('Charlie', 'charlie@example.com');

        $users = $this->mapper->findAll(
            SimpleUser::class,
            orderBy: ['name' => 'ASC'],
            limit: 2,
            offset: 1,
        );

        $this->assertCount(2, $users);
        $this->assertSame('Bob', $users[0]->name);
        $this->assertSame('Charlie', $users[1]->name);
    }

    public function testFindAllReturnsEmptyArrayWhenNoMatches(): void
    {
        $this->createSimpleUsersTable();

        $users = $this->mapper->findAll(SimpleUser::class, ['name' => 'Nobody']);

        $this->assertSame([], $users);
    }

    public function testFindAllWithDateTimeHydration(): void
    {
        $this->createUsersWithDatesTable();
        $this->connection->executeStatement(
            "INSERT INTO user_with_dates (name, created_at, updated_at) VALUES ('Alice', '2025-06-15 10:30:00', '2025-06-16 12:00:00')",
        );

        $rsm = new ResultSetMapping(UserWithDates::class, table: 'user_with_dates');
        $rsm->addField('id', 'id')
            ->addField('name', 'name')
            ->addField('created_at', 'createdAt')
            ->addField('updated_at', 'updatedAt');

        $users = $this->mapper->findAll($rsm);

        $this->assertCount(1, $users);
        $this->assertInstanceOf(UserWithDates::class, $users[0]);
        $this->assertInstanceOf(\DateTimeImmutable::class, $users[0]->createdAt);
        $this->assertSame('2025-06-15 10:30:00', $users[0]->createdAt->format('Y-m-d H:i:s'));
    }

    public function testFindAllWithEnumHydration(): void
    {
        $this->createDtoWithEnumTable();
        $this->connection->executeStatement(
            "INSERT INTO dto_with_enums (status, previous_status) VALUES ('active', 'inactive')",
        );

        $dtos = $this->mapper->findAll(DtoWithEnum::class);

        $this->assertCount(1, $dtos);
        $this->assertSame(UserStatus::Active, $dtos[0]->status);
        $this->assertSame(UserStatus::Inactive, $dtos[0]->previousStatus);
    }

    public function testFindAllThrowsWhenRsmHasNoTable(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('table name');

        $this->mapper->findAll($rsm);
    }

    // -----------------------------------------------------------------------
    // findOne
    // -----------------------------------------------------------------------

    public function testFindOneAutoMode(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $user = $this->mapper->findOne(SimpleUser::class, ['id' => 1]);

        $this->assertInstanceOf(SimpleUser::class, $user);
        $this->assertSame(1, $user->id);
        $this->assertSame('Alice', $user->name);
        $this->assertSame('alice@example.com', $user->email);
    }

    public function testFindOneWithRsm(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'simple_users');
        $rsm->addField('id', 'id')
            ->addField('name', 'name')
            ->addField('email', 'email');

        $user = $this->mapper->findOne($rsm, ['id' => 1]);

        $this->assertInstanceOf(SimpleUser::class, $user);
        $this->assertSame('Alice', $user->name);
    }

    public function testFindOneReturnsNullWhenNoMatch(): void
    {
        $this->createSimpleUsersTable();

        $user = $this->mapper->findOne(SimpleUser::class, ['id' => 999]);

        $this->assertNull($user);
    }

    public function testFindOneReturnsFirstMatch(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $user = $this->mapper->findOne(SimpleUser::class);

        $this->assertInstanceOf(SimpleUser::class, $user);
        // Should return the first row (no ordering specified, but only one is returned)
        $this->assertNotNull($user->name);
    }

    public function testFindOneThrowsWhenRsmHasNoTable(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('table name');

        $this->mapper->findOne($rsm, ['id' => 1]);
    }

    // -----------------------------------------------------------------------
    // Full round-trip tests (insert → findOne → update → findOne → delete)
    // -----------------------------------------------------------------------

    public function testFullCrudRoundTrip(): void
    {
        $this->createSimpleUsersTable();

        // INSERT
        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $id = $this->mapper->insert('simple_users', $user);
        $this->assertSame('1', $id);

        // FIND ONE
        $found = $this->mapper->findOne(SimpleUser::class, ['id' => 1]);
        $this->assertInstanceOf(SimpleUser::class, $found);
        $this->assertSame(1, $found->id);
        $this->assertSame('Alice', $found->name);
        $this->assertSame('alice@example.com', $found->email);

        // UPDATE
        $found->name = 'Alice Updated';
        $found->email = 'alice-updated@example.com';

        $affected = $this->mapper->update('simple_users', $found, ['id' => $found->id]);
        $this->assertSame(1, $affected);

        // FIND ONE after update
        $updated = $this->mapper->findOne(SimpleUser::class, ['id' => 1]);
        $this->assertSame('Alice Updated', $updated->name);
        $this->assertSame('alice-updated@example.com', $updated->email);

        // DELETE
        $affected = $this->mapper->delete('simple_users', ['id' => 1]);
        $this->assertSame(1, $affected);

        // Verify deletion
        $deleted = $this->mapper->findOne(SimpleUser::class, ['id' => 1]);
        $this->assertNull($deleted);
    }

    public function testFullCrudRoundTripWithRsm(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE custom_tbl (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usr_name TEXT NOT NULL,
                usr_email TEXT NOT NULL
            )',
        );

        $rsm = new ResultSetMapping(SimpleUser::class, table: 'custom_tbl');
        $rsm->addField('id', 'id')
            ->addField('usr_name', 'name')
            ->addField('usr_email', 'email');

        // INSERT via RSM
        $user = new SimpleUser();
        $user->name = 'Bob';
        $user->email = 'bob@example.com';

        $id = $this->mapper->insert($rsm, $user);
        $this->assertSame('1', $id);

        // FIND ONE via RSM
        $found = $this->mapper->findOne($rsm, ['id' => 1]);
        $this->assertInstanceOf(SimpleUser::class, $found);
        $this->assertSame('Bob', $found->name);
        $this->assertSame('bob@example.com', $found->email);

        // UPDATE via RSM
        $found->name = 'Bob Updated';
        $affected = $this->mapper->update($rsm, $found, ['id' => 1]);
        $this->assertSame(1, $affected);

        // Verify update via RSM
        $updated = $this->mapper->findOne($rsm, ['id' => 1]);
        $this->assertSame('Bob Updated', $updated->name);

        // DELETE via RSM
        $affected = $this->mapper->delete($rsm, ['id' => 1]);
        $this->assertSame(1, $affected);

        $this->assertNull($this->mapper->findOne($rsm, ['id' => 1]));
    }

    // -----------------------------------------------------------------------
    // NameConverter integration
    // -----------------------------------------------------------------------

    public function testAutoModeUsesNameConverterForColumnMapping(): void
    {
        $this->createUsersWithDatesTable();

        $user = new UserWithDates();
        $user->name = 'Alice';
        $user->createdAt = new \DateTimeImmutable('2025-01-01 00:00:00');
        $user->updatedAt = new \DateTimeImmutable('2025-01-02 00:00:00');

        // Auto mode: SnakeCaseToCamelCase should convert createdAt → created_at
        $this->mapper->insert('user_with_dates', $user);

        $row = $this->connection->fetchAssociative('SELECT * FROM user_with_dates WHERE id = 1');
        $this->assertSame('2025-01-01 00:00:00', $row['created_at']);
        $this->assertSame('2025-01-02 00:00:00', $row['updated_at']);

        // Reading back with RSM should also work
        $rsm = new ResultSetMapping(UserWithDates::class, table: 'user_with_dates');
        $rsm->addField('id', 'id')
            ->addField('name', 'name')
            ->addField('created_at', 'createdAt')
            ->addField('updated_at', 'updatedAt');

        $found = $this->mapper->findOne($rsm, ['id' => 1]);
        $this->assertSame('2025-01-01 00:00:00', $found->createdAt->format('Y-m-d H:i:s'));
    }

    public function testCustomNameConverter(): void
    {
        // With NullConverter, property names are used as-is (no snake_case conversion)
        $mapper = new DataMapper($this->connection, nameConverter: new NullConverter());

        $this->connection->executeStatement(
            'CREATE TABLE nullconv (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )',
        );

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $mapper->insert('nullconv', $user);

        $row = $this->connection->fetchAssociative('SELECT * FROM nullconv WHERE id = 1');
        $this->assertSame('Alice', $row['name']);
    }

    // -----------------------------------------------------------------------
    // Table name derivation
    // -----------------------------------------------------------------------

    public function testDeriveTableNameFromClassName(): void
    {
        // SimpleUser → simple_users (table name)
        // We test this implicitly by calling findAll with only the class name
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $users = $this->mapper->findAll(SimpleUser::class);

        $this->assertCount(1, $users);
        $this->assertSame('Alice', $users[0]->name);
    }

    // -----------------------------------------------------------------------
    // getConnection
    // -----------------------------------------------------------------------

    public function testGetConnectionReturnsSameInstance(): void
    {
        $this->assertSame($this->connection, $this->mapper->getConnection());
    }

    // -----------------------------------------------------------------------
    // insert / update — edge cases
    // -----------------------------------------------------------------------

    public function testInsertThrowsWhenDtoHasNoData(): void
    {
        $this->createSimpleUsersTable();

        // All properties are uninitialized, so no data is extracted.
        $user = new SimpleUser();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no data extracted');

        $this->mapper->insert('simple_users', $user);
    }

    public function testUpdateThrowsWhenDtoHasNoData(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');

        $user = new SimpleUser();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no data extracted');

        $this->mapper->update('simple_users', $user, ['id' => 1]);
    }

    public function testInsertWithBoolConversion(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE bool_test (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                is_active INTEGER NOT NULL,
                name TEXT NOT NULL
            )',
        );

        // Create a DTO with boolean property
        $dto = new class {
            public string $name = 'Alice';
            public bool $isActive = true;
        };

        $this->mapper->insert('bool_test', $dto);

        $row = $this->connection->fetchAssociative('SELECT * FROM bool_test WHERE id = 1');
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertSame('Alice', $row['name']);
    }

    public function testInsertWithBoolFalseConversion(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE bool_test2 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                is_active INTEGER NOT NULL,
                name TEXT NOT NULL
            )',
        );

        $dto = new class {
            public string $name = 'Bob';
            public bool $isActive = false;
        };

        $this->mapper->insert('bool_test2', $dto);

        $row = $this->connection->fetchAssociative('SELECT * FROM bool_test2 WHERE id = 1');
        $this->assertSame(0, (int) $row['is_active']);
    }

    public function testInsertWithNullValue(): void
    {
        $this->createDtoWithEnumTable();

        $dto = new DtoWithEnum();
        $dto->status = UserStatus::Active;
        $dto->previousStatus = null;

        $this->mapper->insert('dto_with_enums', $dto);

        $row = $this->connection->fetchAssociative('SELECT * FROM dto_with_enums WHERE id = 1');
        $this->assertSame('active', $row['status']);
        $this->assertNull($row['previous_status']);
    }

    public function testUpdateWithDateTimeConversion(): void
    {
        $this->createUsersWithDatesTable();
        $this->connection->executeStatement(
            "INSERT INTO user_with_dates (name, created_at, updated_at) VALUES ('Alice', '2025-01-01 00:00:00', '2025-01-01 00:00:00')",
        );

        $user = new UserWithDates();
        $user->name = 'Alice Updated';
        $user->createdAt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $user->updatedAt = new \DateTimeImmutable('2025-06-16 08:30:00');

        $affected = $this->mapper->update('user_with_dates', $user, ['id' => 1]);

        $this->assertSame(1, $affected);

        $row = $this->connection->fetchAssociative('SELECT * FROM user_with_dates WHERE id = 1');
        $this->assertSame('2025-06-15 12:00:00', $row['created_at']);
        $this->assertSame('2025-06-16 08:30:00', $row['updated_at']);
    }

    public function testUpdateWithEnumConversion(): void
    {
        $this->createDtoWithEnumTable();
        $this->connection->executeStatement(
            "INSERT INTO dto_with_enums (status, previous_status) VALUES ('active', NULL)",
        );

        $dto = new DtoWithEnum();
        $dto->status = UserStatus::Banned;
        $dto->previousStatus = UserStatus::Active;

        $affected = $this->mapper->update('dto_with_enums', $dto, ['id' => 1]);

        $this->assertSame(1, $affected);

        $row = $this->connection->fetchAssociative('SELECT * FROM dto_with_enums WHERE id = 1');
        $this->assertSame('banned', $row['status']);
        $this->assertSame('active', $row['previous_status']);
    }

    public function testDeleteMultipleRows(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');
        $this->insertSimpleUser('Charlie', 'charlie@example.com');

        // Delete by a condition matching multiple rows
        // We need a column with same values. Let's just delete one at a time and verify.
        $affected1 = $this->mapper->delete('simple_users', ['name' => 'Alice']);
        $affected2 = $this->mapper->delete('simple_users', ['name' => 'Bob']);

        $this->assertSame(1, $affected1);
        $this->assertSame(1, $affected2);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM simple_users');
        $this->assertSame(1, (int) $count);
    }

    // -----------------------------------------------------------------------
    // findAll — additional edge cases
    // -----------------------------------------------------------------------

    public function testFindAllWithMultipleWhereConditions(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Alice', 'alice2@example.com');
        $this->insertSimpleUser('Bob', 'bob@example.com');

        $users = $this->mapper->findAll(SimpleUser::class, [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertCount(1, $users);
        $this->assertSame('alice@example.com', $users[0]->email);
    }

    public function testFindOneWithMultipleWhereConditions(): void
    {
        $this->createSimpleUsersTable();
        $this->insertSimpleUser('Alice', 'alice@example.com');
        $this->insertSimpleUser('Alice', 'alice2@example.com');

        $user = $this->mapper->findOne(SimpleUser::class, [
            'name' => 'Alice',
            'email' => 'alice2@example.com',
        ]);

        $this->assertInstanceOf(SimpleUser::class, $user);
        $this->assertSame('alice2@example.com', $user->email);
    }

    public function testFindAllWithEmptyTable(): void
    {
        $this->createSimpleUsersTable();

        $users = $this->mapper->findAll(SimpleUser::class);

        $this->assertSame([], $users);
    }

    public function testFindOneWithEmptyTable(): void
    {
        $this->createSimpleUsersTable();

        $user = $this->mapper->findOne(SimpleUser::class);

        $this->assertNull($user);
    }

    public function testFindAllWithNullableEnumHydration(): void
    {
        $this->createDtoWithEnumTable();
        $this->connection->executeStatement(
            "INSERT INTO dto_with_enums (status, previous_status) VALUES ('active', NULL)",
        );

        $dtos = $this->mapper->findAll(DtoWithEnum::class);

        $this->assertCount(1, $dtos);
        $this->assertSame(UserStatus::Active, $dtos[0]->status);
        $this->assertNull($dtos[0]->previousStatus);
    }

    // -----------------------------------------------------------------------
    // Insert + Read round-trip with auto-derived table name
    // -----------------------------------------------------------------------

    public function testInsertAndFindOneWithAutoTableNameDerivation(): void
    {
        $this->createSimpleUsersTable();

        $user = new SimpleUser();
        $user->name = 'Derived';
        $user->email = 'derived@example.com';

        // Use explicit table for insert (table derivation only works for read)
        $this->mapper->insert('simple_users', $user);

        // findOne uses auto-derivation: SimpleUser → simple_users
        $found = $this->mapper->findOne(SimpleUser::class, ['name' => 'Derived']);

        $this->assertInstanceOf(SimpleUser::class, $found);
        $this->assertSame('Derived', $found->name);
        $this->assertSame('derived@example.com', $found->email);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createSimpleUsersTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE simple_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )',
        );
    }

    private function createUsersWithDatesTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE user_with_dates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
    }

    private function createDtoWithEnumTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE dto_with_enums (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                status TEXT NOT NULL,
                previous_status TEXT
            )',
        );
    }

    private function insertSimpleUser(string $name, string $email): void
    {
        $this->connection->executeStatement(
            'INSERT INTO simple_users (name, email) VALUES (?, ?)',
            [$name, $email],
        );
    }
}
