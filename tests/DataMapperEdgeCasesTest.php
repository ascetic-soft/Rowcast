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
