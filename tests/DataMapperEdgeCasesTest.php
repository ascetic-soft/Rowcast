<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\Tests\Fixtures\UserDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use PHPUnit\Framework\TestCase;

final class DataMapperEdgeCasesTest extends TestCase
{
    private Connection $connection;
    private DataMapper $mapper;

    protected function setUp(): void
    {
        $this->connection = new Connection(new \PDO('sqlite::memory:'));
        $this->mapper = new DataMapper($this->connection);
        $this->connection->executeStatement(
            'CREATE TABLE user_dtos (
                id INTEGER PRIMARY KEY,
                email TEXT NOT NULL,
                is_active INTEGER NOT NULL,
                tags TEXT NOT NULL,
                created_at TEXT NULL,
                status TEXT NOT NULL,
                previous_status TEXT NULL
            )',
        );
    }

    public function testInsertAndUpdateThrowWhenNoDataExtracted(): void
    {
        $mapping = Mapping::auto(UserDto::class, 'user_dtos')
            ->ignore('id', 'email', 'isActive', 'tags', 'createdAt', 'status', 'previousStatus');
        $dto = $this->createUser(1, 'a@x.com');

        try {
            $this->mapper->insert($mapping, $dto);
            self::fail('Expected insert to throw on empty extracted data.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->mapper->update($mapping, $dto, ['id' => 1]);
    }

    public function testUpsertThrowsWhenNoDataExtracted(): void
    {
        $mapping = Mapping::auto(UserDto::class, 'user_dtos')
            ->ignore('id', 'email', 'isActive', 'tags', 'createdAt', 'status', 'previousStatus');
        $dto = $this->createUser(11, 'upsert-empty@x.com');

        $this->expectException(\LogicException::class);
        $this->mapper->upsert($mapping, $dto, 'id');
    }

    public function testUpdateAndDeleteValidateWhere(): void
    {
        $dto = $this->createUser(2, 'b@x.com');

        try {
            $this->mapper->update(UserDto::class, $dto, []);
            self::fail('Expected update to require where conditions.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->mapper->delete(UserDto::class, []);
    }

    public function testSaveAndUpsertValidateIdentityAndConflictProperties(): void
    {
        $dto = $this->createUser(3, 'c@x.com');

        try {
            $this->mapper->save(UserDto::class, $dto);
            self::fail('Expected save to require identity properties.');
        } catch (\LogicException) {
        }

        try {
            $this->mapper->upsert(UserDto::class, $dto);
            self::fail('Expected upsert to require conflict properties.');
        } catch (\LogicException) {
        }

        $mapping = Mapping::auto(UserDto::class, 'user_dtos')->ignore('id');
        try {
            $this->mapper->save($mapping, $dto, 'id');
            self::fail('Expected save to fail when identity property is not extracted.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->mapper->upsert($mapping, $dto, 'id');
    }

    public function testBatchMethodsValidationAndNoopBranches(): void
    {
        $dto = $this->createUser(4, 'd@x.com');

        // No-op on empty input
        $this->mapper->batchInsert(UserDto::class, []);
        $this->mapper->batchUpsert(UserDto::class, [], ['id']);
        $this->mapper->batchUpdate(UserDto::class, [], ['id']);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_dtos'));

        try {
            $this->mapper->batchUpsert(UserDto::class, [$dto], []);
            self::fail('Expected batchUpsert to require conflict properties.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->mapper->batchUpdate(UserDto::class, [$dto], []);
    }

    public function testBatchMethodsThrowForInvalidMaxBindParameters(): void
    {
        $dto = $this->createUser(5, 'e@x.com');

        try {
            $this->mapper->batchInsert(UserDto::class, [$dto], 0);
            self::fail('Expected batchInsert to fail for maxBindParameters <= 0.');
        } catch (\LogicException) {
        }

        try {
            $this->mapper->batchUpsert(UserDto::class, [$dto], ['id'], 0);
            self::fail('Expected batchUpsert to fail for maxBindParameters <= 0.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->mapper->batchUpdate(UserDto::class, [$dto], ['id'], 0);
    }

    public function testBatchInsertThrowsWhenColumnsExceedMaxBindParameters(): void
    {
        $dto = $this->createUser(12, 'too-many-cols@x.com');

        $this->expectException(\LogicException::class);
        $this->mapper->batchInsert(UserDto::class, [$dto], 1);
    }

    public function testBatchMethodsThrowForMappingAndIdentityEdgeCases(): void
    {
        $dto = $this->createUser(6, 'f@x.com');

        $emptyMapping = Mapping::auto(UserDto::class, 'user_dtos')
            ->ignore('id', 'email', 'isActive', 'tags', 'createdAt', 'status', 'previousStatus');

        try {
            $this->mapper->batchUpsert($emptyMapping, [$dto], ['id']);
            self::fail('Expected batchUpsert to fail when extracted data is empty.');
        } catch (\LogicException) {
        }

        try {
            $this->mapper->batchInsert($emptyMapping, [$dto]);
            self::fail('Expected batchInsert to fail when extracted data is empty.');
        } catch (\LogicException) {
        }

        $missingIdentityMapping = Mapping::auto(UserDto::class, 'user_dtos')->ignore('id');
        try {
            $this->mapper->batchUpdate($missingIdentityMapping, [$dto], ['id']);
            self::fail('Expected batchUpdate to fail when identity is not extracted.');
        } catch (\LogicException) {
        }

        try {
            $this->mapper->batchUpsert($missingIdentityMapping, [$dto], ['id']);
            self::fail('Expected batchUpsert to fail when conflict property is not extracted.');
        } catch (\LogicException) {
        }

        $allIdentityProperties = ['id', 'email', 'isActive', 'tags', 'createdAt', 'status', 'previousStatus'];
        try {
            $this->mapper->batchUpdate(UserDto::class, [$dto], $allIdentityProperties);
            self::fail('Expected batchUpdate to fail when no columns remain to update.');
        } catch (\LogicException) {
        }

        try {
            $this->mapper->batchUpdate(UserDto::class, [$dto], ['id'], 1);
            self::fail('Expected batchUpdate to fail when required parameters exceed maxBindParameters.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->mapper->batchUpdate(UserDto::class, [$dto], ['previousStatus']);
    }

    public function testInsertDoesNotCallLastInsertId(): void
    {
        $pdo = new class ('sqlite::memory:') extends \PDO {
            public function lastInsertId(?string $name = null): string|false
            {
                throw new \RuntimeException('lastInsertId should not be called by DataMapper::insert().');
            }
        };

        $connection = new Connection($pdo);
        $connection->executeStatement(
            'CREATE TABLE cards (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL
            )',
        );
        $mapper = new DataMapper($connection);

        $card = new class () {
            public string $id;
            public string $title;
        };
        $card->id = 'uuid-1';
        $card->title = 'Card';

        $mapper->insert('cards', $card);

        self::assertSame('1', (string) $connection->fetchOne('SELECT COUNT(*) FROM cards WHERE id = ?', ['uuid-1']));
    }

    private function createUser(int $id, string $email): UserDto
    {
        $user = new UserDto();
        $user->id = $id;
        $user->email = $email;
        $user->isActive = true;
        $user->tags = ['one'];
        $user->createdAt = new \DateTimeImmutable('2026-03-07 12:00:00+00:00');
        $user->status = UserStatus::Active;
        $user->previousStatus = null;

        return $user;
    }
}
