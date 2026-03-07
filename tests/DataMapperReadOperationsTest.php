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

final class DataMapperReadOperationsTest extends TestCase
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
        $this->connection->executeStatement('CREATE TABLE cards (id TEXT PRIMARY KEY, title TEXT NOT NULL, keyword_meta TEXT NULL)');
    }

    public function testFindAllIterateAllHydrateAndExtract(): void
    {
        $u1 = $this->createUser(1, 'a@example.com');
        $u2 = $this->createUser(2, 'b@example.com');
        $this->mapper->insert(UserDto::class, $u1);
        $this->mapper->insert(UserDto::class, $u2);

        $all = $this->mapper->findAll(UserDto::class, orderBy: ['id' => 'ASC'], limit: 2, offset: 0);
        self::assertCount(2, $all);
        self::assertSame(1, $all[0]->id);

        $iterated = iterator_to_array($this->mapper->iterateAll(UserDto::class, orderBy: ['id' => 'ASC']));
        self::assertCount(2, $iterated);
        self::assertSame(2, $iterated[1]->id);

        $row = [
            'id' => '3',
            'email' => 'c@example.com',
            'is_active' => 1,
            'tags' => '["x"]',
            'created_at' => '2026-01-01 00:00:00+00:00',
            'status' => 'active',
            'previous_status' => null,
        ];
        $hydrated = $this->mapper->hydrate(UserDto::class, $row);
        self::assertSame(3, $hydrated->id);

        $hydratedAll = $this->mapper->hydrateAll(UserDto::class, [$row]);
        self::assertCount(1, $hydratedAll);
        self::assertSame(3, $hydratedAll[0]->id);

        $extracted = $this->mapper->extract(UserDto::class, $u1);
        self::assertSame(1, $extracted['id']);
        self::assertSame($this->connection, $this->mapper->getConnection());
    }

    public function testSaveInsertsWhenEntityDoesNotExistAndDeleteRemovesRow(): void
    {
        $user = $this->createUser(10, 'new@example.com');

        $this->mapper->save(UserDto::class, $user, 'id');
        self::assertSame('1', (string) $this->connection->fetchOne('SELECT COUNT(*) FROM user_dtos WHERE id = 10'));

        $deleted = $this->mapper->delete(UserDto::class, ['id' => 10]);
        self::assertSame(1, $deleted);
        self::assertSame('0', (string) $this->connection->fetchOne('SELECT COUNT(*) FROM user_dtos WHERE id = 10'));
    }

    public function testUpdateWithWhereAffectsExpectedRow(): void
    {
        $user = $this->createUser(20, 'old@example.com');
        $this->mapper->insert(UserDto::class, $user);

        $user->email = 'updated@example.com';
        $affected = $this->mapper->update(UserDto::class, $user, ['id' => 20]);

        self::assertSame(1, $affected);
        self::assertSame('updated@example.com', $this->connection->fetchOne('SELECT email FROM user_dtos WHERE id = 20'));
    }

    public function testMappingTargetWorksWithHydrateExtractAndFindAll(): void
    {
        $mapping = Mapping::auto(CardDto::class, 'cards')->column('keyword_meta', 'publishData');
        $card = new CardDto();
        $card->id = 'c1';
        $card->title = 'Card 1';
        $card->publishData = ['score' => 10];
        $this->mapper->insert($mapping, $card);

        $cards = $this->mapper->findAll($mapping);
        self::assertCount(1, $cards);
        self::assertSame(['score' => 10], $cards[0]->publishData);

        $extracted = $this->mapper->extract($mapping, $card);
        self::assertSame('{"score":10}', $extracted['keyword_meta']);
    }

    private function createUser(int $id, string $email): UserDto
    {
        $user = new UserDto();
        $user->id = $id;
        $user->email = $email;
        $user->isActive = true;
        $user->tags = ['one', 'two'];
        $user->createdAt = new \DateTimeImmutable('2026-03-07 12:00:00+00:00');
        $user->status = UserStatus::Active;
        $user->previousStatus = null;

        return $user;
    }
}
