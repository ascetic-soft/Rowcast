<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Persistence;

use AsceticSoft\Rowcast\Mapping\NameConverter\NullConverter;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;
use AsceticSoft\Rowcast\Persistence\DtoExtractor;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\DtoWithEnum;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\SimpleUser;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserStatus;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserWithDates;
use PHPUnit\Framework\TestCase;

final class DtoExtractorTest extends TestCase
{
    private DtoExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new DtoExtractor();
    }

    public function testExtractAutoMode(): void
    {
        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $data = $this->extractor->extract($user);

        self::assertSame('Alice', $data['name']);
        self::assertSame('alice@example.com', $data['email']);
    }

    public function testExtractSkipsUninitializedProperties(): void
    {
        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $data = $this->extractor->extract($user);

        self::assertArrayNotHasKey('id', $data);
    }

    public function testExtractWithRsm(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class, 'users');
        $rsm->addField('usr_name', 'name')
            ->addField('usr_email', 'email');

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $data = $this->extractor->extract($user, $rsm);

        self::assertSame('Alice', $data['usr_name']);
        self::assertSame('alice@example.com', $data['usr_email']);
        self::assertArrayNotHasKey('name', $data);
        self::assertArrayNotHasKey('id', $data);
    }

    public function testExtractConvertsBool(): void
    {
        $dto = new class () {
            public bool $active = true;
        };

        $data = $this->extractor->extract($dto);

        self::assertSame(1, $data['active']);
    }

    public function testExtractConvertsBoolFalse(): void
    {
        $dto = new class () {
            public bool $active = false;
        };

        $data = $this->extractor->extract($dto);

        self::assertSame(0, $data['active']);
    }

    public function testExtractConvertsEnum(): void
    {
        $dto = new DtoWithEnum();
        $dto->status = UserStatus::Active;
        $dto->previousStatus = null;

        $data = $this->extractor->extract($dto);

        self::assertSame('active', $data['status']);
        self::assertNull($data['previous_status']);
    }

    public function testExtractConvertsDateTime(): void
    {
        $dto = new UserWithDates();
        $dto->name = 'Alice';
        $dto->createdAt = new \DateTimeImmutable('2025-06-15 10:30:00');
        $dto->updatedAt = new \DateTimeImmutable('2025-06-16 12:00:00');

        $data = $this->extractor->extract($dto);

        self::assertSame('2025-06-15 10:30:00', $data['created_at']);
        self::assertSame('2025-06-16 12:00:00', $data['updated_at']);
    }

    public function testExtractWithNullConverter(): void
    {
        $extractor = new DtoExtractor(new NullConverter());

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $data = $extractor->extract($user);

        self::assertArrayHasKey('name', $data);
        self::assertArrayHasKey('email', $data);
    }

    public function testExtractAllUninitializedReturnsEmptyArray(): void
    {
        $user = new SimpleUser();

        $data = $this->extractor->extract($user);

        self::assertSame([], $data);
    }

    public function testExtractWithRsmSkipsMissingProperties(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class, 'users');
        $rsm->addField('nonexistent_col', 'nonexistentProperty');

        $user = new SimpleUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $data = $this->extractor->extract($user, $rsm);

        self::assertSame([], $data);
    }
}
