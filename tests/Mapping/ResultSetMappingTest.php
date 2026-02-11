<?php

declare(strict_types=1);

namespace Rowcast\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Rowcast\Mapping\ResultSetMapping;

final class ResultSetMappingTest extends TestCase
{
    public function testConstructorSetsClassAndTable(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class, 'users');

        self::assertSame(\stdClass::class, $rsm->getClassName());
        self::assertSame('users', $rsm->getTable());
    }

    public function testConstructorDefaultsTableToNull(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        self::assertNull($rsm->getTable());
    }

    public function testAddFieldReturnsSelfForFluency(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        $result = $rsm->addField('user_name', 'name');

        self::assertSame($rsm, $result);
    }

    public function testAddFieldStoresMapping(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class, 'users');
        $rsm->addField('user_name', 'name')
            ->addField('id', 'id')
            ->addField('created_at', 'createdAt');

        self::assertSame([
            'user_name' => 'name',
            'id' => 'id',
            'created_at' => 'createdAt',
        ], $rsm->getFields());
    }

    public function testAddFieldOverwritesDuplicateColumn(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('col', 'first');
        $rsm->addField('col', 'second');

        self::assertSame('second', $rsm->getPropertyName('col'));
        self::assertCount(1, $rsm->getFields());
    }

    public function testGetPropertyNameReturnsCorrectProperty(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('user_name', 'name');

        self::assertSame('name', $rsm->getPropertyName('user_name'));
    }

    public function testGetPropertyNameReturnsNullForUnknownColumn(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        self::assertNull($rsm->getPropertyName('unknown'));
    }

    public function testGetColumnNameReturnsCorrectColumn(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('user_name', 'name');

        self::assertSame('user_name', $rsm->getColumnName('name'));
    }

    public function testGetColumnNameReturnsNullForUnknownProperty(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        self::assertNull($rsm->getColumnName('unknown'));
    }

    public function testHasColumnReturnsTrueForMappedColumn(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('user_name', 'name');

        self::assertTrue($rsm->hasColumn('user_name'));
    }

    public function testHasColumnReturnsFalseForUnmappedColumn(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        self::assertFalse($rsm->hasColumn('unknown'));
    }

    public function testHasPropertyReturnsTrueForMappedProperty(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('user_name', 'name');

        self::assertTrue($rsm->hasProperty('name'));
    }

    public function testHasPropertyReturnsFalseForUnmappedProperty(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        self::assertFalse($rsm->hasProperty('unknown'));
    }

    // --- fromArray tests ---

    public function testFromArrayCreatesFullMapping(): void
    {
        $rsm = ResultSetMapping::fromArray([
            'class' => \stdClass::class,
            'table' => 'users',
            'fields' => [
                'user_name' => 'name',
                'id' => 'id',
                'created_at' => 'createdAt',
            ],
        ]);

        self::assertSame(\stdClass::class, $rsm->getClassName());
        self::assertSame('users', $rsm->getTable());
        self::assertSame([
            'user_name' => 'name',
            'id' => 'id',
            'created_at' => 'createdAt',
        ], $rsm->getFields());
    }

    public function testFromArrayWithoutTable(): void
    {
        $rsm = ResultSetMapping::fromArray([
            'class' => \stdClass::class,
            'fields' => [
                'id' => 'id',
            ],
        ]);

        self::assertNull($rsm->getTable());
        self::assertSame(['id' => 'id'], $rsm->getFields());
    }

    public function testFromArrayWithoutFields(): void
    {
        $rsm = ResultSetMapping::fromArray([
            'class' => \stdClass::class,
            'table' => 'users',
        ]);

        self::assertSame([], $rsm->getFields());
    }

    public function testFromArrayThrowsWithoutClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "class" key is required');

        ResultSetMapping::fromArray([
            'table' => 'users',
            'fields' => ['id' => 'id'],
        ]);
    }

    public function testGetFieldsReturnsEmptyArrayByDefault(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);

        self::assertSame([], $rsm->getFields());
    }

    // --- Additional edge cases ---

    public function testMultipleFieldsMappedToSameProperty(): void
    {
        // This is technically possible but the last one wins for reverse lookup
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('col_a', 'name');
        $rsm->addField('col_b', 'name');

        // Both columns map to 'name'
        self::assertSame('name', $rsm->getPropertyName('col_a'));
        self::assertSame('name', $rsm->getPropertyName('col_b'));

        // hasProperty checks if 'name' appears in values
        self::assertTrue($rsm->hasProperty('name'));
    }

    public function testGetColumnNameReturnsFirstMatchWhenMultipleMap(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('col_a', 'name');
        $rsm->addField('col_b', 'name');

        // array_flip will keep the last key, so col_b -> name flipped = name -> col_b
        $column = $rsm->getColumnName('name');
        self::assertSame('col_b', $column);
    }

    public function testFromArrayWithEmptyFieldsArray(): void
    {
        $rsm = ResultSetMapping::fromArray([
            'class' => \stdClass::class,
            'fields' => [],
        ]);

        self::assertSame([], $rsm->getFields());
    }

    public function testChainingMultipleAddFieldCalls(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class, 'test_table');
        $result = $rsm->addField('a', 'propA')
            ->addField('b', 'propB')
            ->addField('c', 'propC');

        self::assertSame($rsm, $result);
        self::assertCount(3, $rsm->getFields());
    }

    public function testHasColumnAndHasPropertyAfterMultipleFields(): void
    {
        $rsm = new ResultSetMapping(\stdClass::class);
        $rsm->addField('first_name', 'firstName')
            ->addField('last_name', 'lastName')
            ->addField('email_address', 'email');

        self::assertTrue($rsm->hasColumn('first_name'));
        self::assertTrue($rsm->hasColumn('last_name'));
        self::assertTrue($rsm->hasColumn('email_address'));
        self::assertFalse($rsm->hasColumn('phone'));

        self::assertTrue($rsm->hasProperty('firstName'));
        self::assertTrue($rsm->hasProperty('lastName'));
        self::assertTrue($rsm->hasProperty('email'));
        self::assertFalse($rsm->hasProperty('phone'));
    }
}
