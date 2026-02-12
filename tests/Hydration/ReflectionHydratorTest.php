<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Tests\Hydration;

use AsceticSoft\Rowcast\Hydration\HydratorInterface;
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;
use AsceticSoft\Rowcast\Mapping\NameConverter\NullConverter;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\AllScalarsDto;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\ConstructorDto;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\DtoWithEnum;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\NullableDto;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\SimpleUser;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UntypedDto;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserStatus;
use AsceticSoft\Rowcast\Tests\Hydration\Fixtures\UserWithDates;
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReflectionHydratorTest extends TestCase
{
    private ReflectionHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ReflectionHydrator();
    }

    // -------------------------------------------------------
    // Interface contract
    // -------------------------------------------------------

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(HydratorInterface::class, $this->hydrator);
    }

    // -------------------------------------------------------
    // Auto mode (NameConverter) — hydrate()
    // -------------------------------------------------------

    public function testHydrateAutoSimpleScalars(): void
    {
        $row = ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com'];

        $user = $this->hydrator->hydrate(SimpleUser::class, $row);

        self::assertInstanceOf(SimpleUser::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame('Alice', $user->name);
        self::assertSame('alice@example.com', $user->email);
    }

    public function testHydrateAutoWithSnakeCaseColumns(): void
    {
        $row = [
            'id' => '5',
            'name' => 'Bob',
            'created_at' => '2025-06-15 10:30:00',
            'updated_at' => '2025-12-01 08:00:00',
        ];

        $dto = $this->hydrator->hydrate(UserWithDates::class, $row);

        self::assertInstanceOf(UserWithDates::class, $dto);
        self::assertSame(5, $dto->id);
        self::assertSame('Bob', $dto->name);
        self::assertInstanceOf(DateTimeImmutable::class, $dto->createdAt);
        self::assertSame('2025-06-15 10:30:00', $dto->createdAt->format('Y-m-d H:i:s'));
        self::assertInstanceOf(DateTimeImmutable::class, $dto->updatedAt);
        self::assertSame('2025-12-01 08:00:00', $dto->updatedAt->format('Y-m-d H:i:s'));
    }

    public function testHydrateAutoAllScalarTypes(): void
    {
        $row = [
            'int_val' => '42',
            'float_val' => '3.14',
            'bool_val' => '1',
            'string_val' => 'hello',
        ];

        $dto = $this->hydrator->hydrate(AllScalarsDto::class, $row);

        self::assertSame(42, $dto->intVal);
        self::assertSame(3.14, $dto->floatVal);
        self::assertTrue($dto->boolVal);
        self::assertSame('hello', $dto->stringVal);
    }

    public function testHydrateAutoWithEnum(): void
    {
        $row = [
            'id' => '1',
            'status' => 'active',
            'previous_status' => 'inactive',
        ];

        $dto = $this->hydrator->hydrate(DtoWithEnum::class, $row);

        self::assertSame(1, $dto->id);
        self::assertSame(UserStatus::Active, $dto->status);
        self::assertSame(UserStatus::Inactive, $dto->previousStatus);
    }

    public function testHydrateAutoNullableWithValues(): void
    {
        $row = [
            'id' => '1',
            'nickname' => 'ally',
            'deleted_at' => '2025-01-01 00:00:00',
        ];

        $dto = $this->hydrator->hydrate(NullableDto::class, $row);

        self::assertSame(1, $dto->id);
        self::assertSame('ally', $dto->nickname);
        self::assertInstanceOf(DateTimeImmutable::class, $dto->deletedAt);
    }

    public function testHydrateAutoNullableWithNulls(): void
    {
        $row = [
            'id' => '1',
            'nickname' => null,
            'deleted_at' => null,
        ];

        $dto = $this->hydrator->hydrate(NullableDto::class, $row);

        self::assertSame(1, $dto->id);
        self::assertNull($dto->nickname);
        self::assertNull($dto->deletedAt);
    }

    public function testHydrateAutoNullableEnumWithNull(): void
    {
        $row = [
            'id' => '2',
            'status' => 'banned',
            'previous_status' => null,
        ];

        $dto = $this->hydrator->hydrate(DtoWithEnum::class, $row);

        self::assertSame(2, $dto->id);
        self::assertSame(UserStatus::Banned, $dto->status);
        self::assertNull($dto->previousStatus);
    }

    public function testHydrateAutoSkipsMissingColumns(): void
    {
        // Only 'id' is provided — 'name' and 'email' columns are absent in the row.
        $row = ['id' => '10'];

        $user = $this->hydrator->hydrate(SimpleUser::class, $row);

        self::assertSame(10, $user->id);
        // name and email are not set — they remain uninitialized
    }

    public function testHydrateAutoUntypedProperties(): void
    {
        $row = ['id' => 42, 'name' => 'raw'];

        $dto = $this->hydrator->hydrate(UntypedDto::class, $row);

        self::assertSame(42, $dto->id);
        self::assertSame('raw', $dto->name);
    }

    // -------------------------------------------------------
    // RSM mode (explicit mapping) — hydrate()
    // -------------------------------------------------------

    public function testHydrateWithRsm(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class, 'users');
        $rsm->addField('usr_id', 'id')
            ->addField('usr_name', 'name')
            ->addField('usr_email', 'email');

        $row = [
            'usr_id' => '99',
            'usr_name' => 'Charlie',
            'usr_email' => 'charlie@example.com',
        ];

        $user = $this->hydrator->hydrate(SimpleUser::class, $row, $rsm);

        self::assertInstanceOf(SimpleUser::class, $user);
        self::assertSame(99, $user->id);
        self::assertSame('Charlie', $user->name);
        self::assertSame('charlie@example.com', $user->email);
    }

    public function testHydrateWithRsmSkipsMissingColumns(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class, 'users');
        $rsm->addField('usr_id', 'id')
            ->addField('usr_name', 'name')
            ->addField('usr_email', 'email');

        // Only usr_id is present
        $row = ['usr_id' => '7'];

        $user = $this->hydrator->hydrate(SimpleUser::class, $row, $rsm);

        self::assertSame(7, $user->id);
    }

    public function testHydrateWithRsmIgnoresUnmappedColumns(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class, 'users');
        $rsm->addField('usr_id', 'id');

        $row = [
            'usr_id' => '3',
            'usr_name' => 'Ignored',
            'extra_col' => 'also ignored',
        ];

        $user = $this->hydrator->hydrate(SimpleUser::class, $row, $rsm);

        self::assertSame(3, $user->id);
    }

    public function testHydrateWithRsmAndDateTimeCasting(): void
    {
        $rsm = new ResultSetMapping(UserWithDates::class, 'users');
        $rsm->addField('user_id', 'id')
            ->addField('full_name', 'name')
            ->addField('reg_date', 'createdAt')
            ->addField('mod_date', 'updatedAt');

        $row = [
            'user_id' => '1',
            'full_name' => 'Dave',
            'reg_date' => '2024-01-15 12:00:00',
            'mod_date' => '2025-03-20 18:30:00',
        ];

        $dto = $this->hydrator->hydrate(UserWithDates::class, $row, $rsm);

        self::assertSame(1, $dto->id);
        self::assertSame('Dave', $dto->name);
        self::assertSame('2024-01-15 12:00:00', $dto->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-20 18:30:00', $dto->updatedAt->format('Y-m-d H:i:s'));
    }

    // -------------------------------------------------------
    // Object creation without constructor
    // -------------------------------------------------------

    public function testCreatesObjectWithoutCallingConstructor(): void
    {
        $row = ['id' => '1', 'name' => 'Test'];

        $dto = $this->hydrator->hydrate(ConstructorDto::class, $row);

        self::assertInstanceOf(ConstructorDto::class, $dto);
        self::assertSame(1, $dto->id);
        self::assertSame('Test', $dto->name);
        self::assertFalse($dto->constructorCalled);
    }

    // -------------------------------------------------------
    // hydrateAll()
    // -------------------------------------------------------

    public function testHydrateAllAutoMode(): void
    {
        $rows = [
            ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com'],
            ['id' => '3', 'name' => 'Charlie', 'email' => 'charlie@example.com'],
        ];

        $users = $this->hydrator->hydrateAll(SimpleUser::class, $rows);

        self::assertCount(3, $users);
        self::assertContainsOnlyInstancesOf(SimpleUser::class, $users);

        self::assertSame(1, $users[0]->id);
        self::assertSame('Alice', $users[0]->name);

        self::assertSame(2, $users[1]->id);
        self::assertSame('Bob', $users[1]->name);

        self::assertSame(3, $users[2]->id);
        self::assertSame('Charlie', $users[2]->name);
    }

    public function testHydrateAllWithRsm(): void
    {
        $rsm = new ResultSetMapping(SimpleUser::class, 'users');
        $rsm->addField('u_id', 'id')
            ->addField('u_name', 'name')
            ->addField('u_email', 'email');

        $rows = [
            ['u_id' => '10', 'u_name' => 'X', 'u_email' => 'x@test.com'],
            ['u_id' => '20', 'u_name' => 'Y', 'u_email' => 'y@test.com'],
        ];

        $users = $this->hydrator->hydrateAll(SimpleUser::class, $rows, $rsm);

        self::assertCount(2, $users);
        self::assertSame(10, $users[0]->id);
        self::assertSame('X', $users[0]->name);
        self::assertSame(20, $users[1]->id);
        self::assertSame('Y', $users[1]->name);
    }

    public function testHydrateAllReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->hydrator->hydrateAll(SimpleUser::class, []);

        self::assertSame([], $result);
    }

    // -------------------------------------------------------
    // Custom NameConverter
    // -------------------------------------------------------

    public function testHydrateAutoWithNullConverter(): void
    {
        $hydrator = new ReflectionHydrator(nameConverter: new NullConverter());

        // Column names must exactly match property names when using NullConverter
        $row = ['id' => '1', 'name' => 'Eve', 'email' => 'eve@example.com'];

        $user = $hydrator->hydrate(SimpleUser::class, $row);

        self::assertSame(1, $user->id);
        self::assertSame('Eve', $user->name);
        self::assertSame('eve@example.com', $user->email);
    }

    public function testHydrateAutoWithNullConverterDoesNotMapSnakeCase(): void
    {
        $hydrator = new ReflectionHydrator(nameConverter: new NullConverter());

        // NullConverter expects exact property names; snake_case columns won't match
        $row = [
            'id' => '1',
            'name' => 'Frank',
            'created_at' => '2025-01-01 00:00:00',  // won't match 'createdAt'
            'updated_at' => '2025-06-01 00:00:00',  // won't match 'updatedAt'
        ];

        $dto = $hydrator->hydrate(UserWithDates::class, $row);

        self::assertSame(1, $dto->id);
        self::assertSame('Frank', $dto->name);
        // createdAt and updatedAt remain uninitialized because columns don't match
    }

    // -------------------------------------------------------
    // Custom TypeCaster
    // -------------------------------------------------------

    public function testHydrateWithCustomTypeCasterRegistry(): void
    {
        $registry = TypeCasterRegistry::createDefault();
        $hydrator = new ReflectionHydrator(typeCaster: $registry);

        $row = ['id' => '77', 'name' => 'Grace', 'email' => 'grace@example.com'];
        $user = $hydrator->hydrate(SimpleUser::class, $row);

        self::assertSame(77, $user->id);
        self::assertSame('Grace', $user->name);
    }

    // -------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------

    public function testHydrateWithEmptyRow(): void
    {
        $user = $this->hydrator->hydrate(SimpleUser::class, []);

        self::assertInstanceOf(SimpleUser::class, $user);
        // All properties remain uninitialized
    }

    public function testHydrateWithExtraColumnsInAutoMode(): void
    {
        $row = [
            'id' => '5',
            'name' => 'Hank',
            'email' => 'hank@example.com',
            'nonexistent_column' => 'ignored',
            'another_extra' => 123,
        ];

        // Extra columns that don't match any property are simply ignored
        $user = $this->hydrator->hydrate(SimpleUser::class, $row);

        self::assertSame(5, $user->id);
        self::assertSame('Hank', $user->name);
        self::assertSame('hank@example.com', $user->email);
    }

    public function testEachObjectInHydrateAllIsIndependent(): void
    {
        $rows = [
            ['id' => '1', 'name' => 'A', 'email' => 'a@a.com'],
            ['id' => '2', 'name' => 'B', 'email' => 'b@b.com'],
        ];

        $users = $this->hydrator->hydrateAll(SimpleUser::class, $rows);

        self::assertNotSame($users[0], $users[1]);
        self::assertSame(1, $users[0]->id);
        self::assertSame(2, $users[1]->id);
    }

    public function testHydrateWithBoolFalseValue(): void
    {
        $row = [
            'int_val' => '0',
            'float_val' => '0.0',
            'bool_val' => '0',
            'string_val' => '',
        ];

        $dto = $this->hydrator->hydrate(AllScalarsDto::class, $row);

        self::assertSame(0, $dto->intVal);
        self::assertSame(0.0, $dto->floatVal);
        self::assertFalse($dto->boolVal);
        self::assertSame('', $dto->stringVal);
    }

    // -------------------------------------------------------
    // Mixed type property (no casting)
    // -------------------------------------------------------

    public function testHydrateAutoMixedTypePropertyGetsRawValue(): void
    {
        // Create an anonymous class with a mixed type property
        $className = \get_class(new class () {
            public mixed $value;
        });

        $row = ['value' => 'raw-string'];

        $hydrator = new ReflectionHydrator(nameConverter: new NullConverter());
        $dto = $hydrator->hydrate($className, $row);

        self::assertSame('raw-string', $dto->value);
    }

    // -------------------------------------------------------
    // Union type property (no casting)
    // -------------------------------------------------------

    public function testHydrateAutoUnionTypePropertyGetsRawValue(): void
    {
        $className = \get_class(new class () {
            public int|string $value;
        });

        $row = ['value' => 'raw-value'];

        $hydrator = new ReflectionHydrator(nameConverter: new NullConverter());
        $dto = $hydrator->hydrate($className, $row);

        self::assertSame('raw-value', $dto->value);
    }

    // -------------------------------------------------------
    // hydrateAll preserves order
    // -------------------------------------------------------

    public function testHydrateAllPreservesOrder(): void
    {
        $rows = [
            ['id' => '3', 'name' => 'C', 'email' => 'c@test.com'],
            ['id' => '1', 'name' => 'A', 'email' => 'a@test.com'],
            ['id' => '2', 'name' => 'B', 'email' => 'b@test.com'],
        ];

        $users = $this->hydrator->hydrateAll(SimpleUser::class, $rows);

        self::assertSame(3, $users[0]->id);
        self::assertSame(1, $users[1]->id);
        self::assertSame(2, $users[2]->id);
    }

    // -------------------------------------------------------
    // RSM with enum types
    // -------------------------------------------------------

    public function testHydrateWithRsmAndEnumCasting(): void
    {
        $rsm = new ResultSetMapping(DtoWithEnum::class, 'enums');
        $rsm->addField('pk', 'id')
            ->addField('user_status', 'status')
            ->addField('prev_status', 'previousStatus');

        $row = [
            'pk' => '1',
            'user_status' => 'active',
            'prev_status' => 'inactive',
        ];

        $dto = $this->hydrator->hydrate(DtoWithEnum::class, $row, $rsm);

        self::assertSame(1, $dto->id);
        self::assertSame(UserStatus::Active, $dto->status);
        self::assertSame(UserStatus::Inactive, $dto->previousStatus);
    }

    // -------------------------------------------------------
    // RSM with nullable enum
    // -------------------------------------------------------

    public function testHydrateWithRsmAndNullableEnum(): void
    {
        $rsm = new ResultSetMapping(DtoWithEnum::class, 'enums');
        $rsm->addField('pk', 'id')
            ->addField('user_status', 'status')
            ->addField('prev_status', 'previousStatus');

        $row = [
            'pk' => '5',
            'user_status' => 'banned',
            'prev_status' => null,
        ];

        $dto = $this->hydrator->hydrate(DtoWithEnum::class, $row, $rsm);

        self::assertSame(5, $dto->id);
        self::assertSame(UserStatus::Banned, $dto->status);
        self::assertNull($dto->previousStatus);
    }

    // -------------------------------------------------------
    // Large batch hydration
    // -------------------------------------------------------

    public function testHydrateAllWithLargeBatch(): void
    {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = ['id' => (string) $i, 'name' => "User$i", 'email' => "user$i@test.com"];
        }

        $users = $this->hydrator->hydrateAll(SimpleUser::class, $rows);

        self::assertCount(100, $users);
        self::assertSame(0, $users[0]->id);
        self::assertSame(99, $users[99]->id);
    }

    // -------------------------------------------------------
    // Default constructor parameters
    // -------------------------------------------------------

    public function testDefaultConstructorUsesDefaultNameConverter(): void
    {
        $hydrator = new ReflectionHydrator();

        // SnakeCaseToCamelCase should be the default
        $row = ['created_at' => '2025-01-01 00:00:00', 'id' => '1', 'name' => 'Test', 'updated_at' => '2025-06-01 00:00:00'];
        $dto = $hydrator->hydrate(UserWithDates::class, $row);

        self::assertSame('2025-01-01 00:00:00', $dto->createdAt->format('Y-m-d H:i:s'));
    }
}
