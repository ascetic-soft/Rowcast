<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests;

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\Rowcast\Mapping;
use AsceticSoft\Rowcast\Tests\Fixtures\CardDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserDto;
use AsceticSoft\Rowcast\Tests\Fixtures\UserStatus;
use PHPUnit\Framework\TestCase;

final class V2DataMapperTest extends TestCase
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
        $this->connection->executeStatement(
            'CREATE TABLE cards (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                keyword_meta TEXT NULL
            )',
        );
    }

    public function testInsertAndFindOne(): void
    {
        $user = $this->createUser(1, 'a@x.com', UserStatus::Active);

        $this->mapper->insert(UserDto::class, $user);
        $found = $this->mapper->findOne(UserDto::class, ['id' => 1]);

        self::assertInstanceOf(UserDto::class, $found);
        self::assertSame('a@x.com', $found->email);
        self::assertTrue($found->isActive);
        self::assertSame(['one', 'two'], $found->tags);
        self::assertSame(UserStatus::Active, $found->status);
    }

    public function testSaveUpdatesExistingRow(): void
    {
        $user = $this->createUser(2, 'old@x.com', UserStatus::Active);
        $this->mapper->insert(UserDto::class, $user);

        $user->email = 'new@x.com';
        $this->mapper->save(UserDto::class, $user, 'id');

        /** @var UserDto $found */
        $found = $this->mapper->findOne(UserDto::class, ['id' => 2]);
        self::assertSame('new@x.com', $found->email);
    }

    public function testUpsertInsertsAndThenUpdates(): void
    {
        $user = $this->createUser(3, 'first@x.com', UserStatus::Active);
        self::assertSame(1, $this->mapper->upsert(UserDto::class, $user, 'id'));

        $user->email = 'second@x.com';
        self::assertSame(1, $this->mapper->upsert(UserDto::class, $user, 'id'));

        /** @var UserDto $found */
        $found = $this->mapper->findOne(UserDto::class, ['id' => 3]);
        self::assertSame('second@x.com', $found->email);
    }

    public function testMappingOverrideIsUsedForHydrateAndExtract(): void
    {
        $mapping = Mapping::auto(CardDto::class, 'cards')
            ->column('keyword_meta', 'publishData');

        $card = new CardDto();
        $card->id = 'c-1';
        $card->title = 'Card';
        $card->publishData = ['score' => 10];

        $this->mapper->insert($mapping, $card);

        $found = $this->mapper->findOne($mapping, ['id' => 'c-1']);
        self::assertInstanceOf(CardDto::class, $found);
        self::assertSame(['score' => 10], $found->publishData);
    }

    public function testBatchInsertSupportsChunkingOnSqlite(): void
    {
        $users = [];
        for ($i = 1; $i <= 150; ++$i) {
            $users[] = $this->createUser(
                $i,
                'insert-' . $i . '@x.com',
                $i % 2 === 0 ? UserStatus::Active : UserStatus::Inactive,
            );
        }

        $this->mapper->batchInsert(UserDto::class, $users, 21);

        self::assertSame(150, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_dtos'));
        self::assertSame(
            'insert-150@x.com',
            $this->connection->fetchOne('SELECT email FROM user_dtos WHERE id = 150'),
        );
    }

    public function testBatchUpsertSupportsChunkingOnSqlite(): void
    {
        $initial = [];
        for ($i = 1; $i <= 150; ++$i) {
            $initial[] = $this->createUser($i, 'seed-' . $i . '@x.com', UserStatus::Active);
        }
        $this->mapper->batchInsert(UserDto::class, $initial, 21);

        $upsert = [];
        for ($i = 1; $i <= 160; ++$i) {
            $upsert[] = $this->createUser($i, 'upsert-' . $i . '@x.com', UserStatus::Inactive);
        }

        $this->mapper->batchUpsert(UserDto::class, $upsert, ['id'], 21);

        self::assertSame(160, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_dtos'));
        self::assertSame(
            'upsert-1@x.com',
            $this->connection->fetchOne('SELECT email FROM user_dtos WHERE id = 1'),
        );
        self::assertSame(
            'upsert-160@x.com',
            $this->connection->fetchOne('SELECT email FROM user_dtos WHERE id = 160'),
        );
    }

    public function testBatchUpdateUpdatesRowsByIdentity(): void
    {
        $initial = [
            $this->createUser(1, 'before-1@x.com', UserStatus::Active),
            $this->createUser(2, 'before-2@x.com', UserStatus::Active),
            $this->createUser(3, 'before-3@x.com', UserStatus::Inactive),
        ];
        $this->mapper->batchInsert(UserDto::class, $initial, 21);

        $updated = [
            $this->createUser(1, 'after-1@x.com', UserStatus::Inactive),
            $this->createUser(2, 'after-2@x.com', UserStatus::Inactive),
            $this->createUser(3, 'after-3@x.com', UserStatus::Active),
        ];

        $this->mapper->batchUpdate(UserDto::class, $updated, ['id'], 21);

        self::assertSame(
            'after-2@x.com',
            $this->connection->fetchOne('SELECT email FROM user_dtos WHERE id = 2'),
        );
        self::assertSame(
            'active',
            $this->connection->fetchOne('SELECT status FROM user_dtos WHERE id = 3'),
        );
    }

    private function createUser(int $id, string $email, UserStatus $status): UserDto
    {
        $user = new UserDto();
        $user->id = $id;
        $user->email = $email;
        $user->isActive = true;
        $user->tags = ['one', 'two'];
        $user->createdAt = new \DateTimeImmutable('2026-03-07 12:00:00+00:00');
        $user->status = $status;
        $user->previousStatus = null;

        return $user;
    }
}
