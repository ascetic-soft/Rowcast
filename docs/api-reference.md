---
title: API Reference
layout: default
nav_order: 9
---

# API Reference
{: .no_toc }

Complete reference for all public classes and methods.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Connection

`AsceticSoft\Rowcast\Connection`

A thin PDO wrapper with convenience methods and transaction support.

### Factory

```php
public static function create(
    string $dsn,
    ?string $username = null,
    ?string $password = null,
    array $options = [],
    bool $nestTransactions = false,
): self
```

### Constructor

```php
public function __construct(\PDO $pdo, bool $nestTransactions = false)
```

### Query Methods

| Method | Returns | Description |
|:-------|:--------|:------------|
| `executeQuery(string $sql, array $params = [])` | `\PDOStatement` | Execute a SELECT query |
| `executeStatement(string $sql, array $params = [])` | `int` | Execute INSERT/UPDATE/DELETE, returns affected rows |
| `fetchAllAssociative(string $sql, array $params = [])` | `array` | Fetch all rows as associative arrays |
| `fetchAssociative(string $sql, array $params = [])` | `array\|false` | Fetch a single row |
| `fetchOne(string $sql, array $params = [])` | `mixed` | Fetch a single scalar value |
| `toIterable(string $sql, array $params = [])` | `iterable` | Return results as a generator |
| `lastInsertId(?string $name = null)` | `string\|false` | Get the last insert ID |

### Transaction Methods

| Method | Returns | Description |
|:-------|:--------|:------------|
| `beginTransaction()` | `void` | Start a transaction (or savepoint) |
| `commit()` | `void` | Commit the transaction (or release savepoint) |
| `rollBack()` | `void` | Roll back the transaction (or to savepoint) |
| `transactional(callable $callback)` | `mixed` | Execute in a transaction with auto commit/rollback |
| `getTransactionNestingLevel()` | `int` | Get current nesting depth |

### Other Methods

| Method | Returns | Description |
|:-------|:--------|:------------|
| `createQueryBuilder()` | `QueryBuilder` | Create a new query builder |
| `getPdo()` | `\PDO` | Get the underlying PDO instance |

---

## DataMapper

`AsceticSoft\Rowcast\DataMapper`

### Constructor

```php
public function __construct(
    Connection $connection,
    ?NameConverterInterface $nameConverter = null,
    ?HydratorInterface $hydrator = null,
)
```

### Methods

| Method | Returns | Description |
|:-------|:--------|:------------|
| `insert(string\|ResultSetMapping $target, object $dto)` | `string\|false` | Insert a DTO, returns last insert ID |
| `update(string\|ResultSetMapping $target, object $dto, array $where)` | `int` | Update rows, returns affected count |
| `delete(string\|ResultSetMapping $target, array $where)` | `int` | Delete rows, returns affected count |
| `findAll(string\|ResultSetMapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null)` | `array` | Find all matching rows as DTOs |
| `iterateAll(string\|ResultSetMapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null)` | `iterable` | Iterate matching rows as DTOs (generator) |
| `findOne(string\|ResultSetMapping $target, array $where = [])` | `object\|null` | Find a single row as a DTO |
| `getConnection()` | `Connection` | Get the underlying connection |

---

## ResultSetMapping

`AsceticSoft\Rowcast\Mapping\ResultSetMapping`

### Constructor

```php
public function __construct(string $className, ?string $table = null)
```

### Methods

| Method | Returns | Description |
|:-------|:--------|:------------|
| `addField(string $column, string $property)` | `self` | Map a column to a property |
| `getClassName()` | `string` | Get the DTO class name |
| `getTable()` | `?string` | Get the table name |
| `getFields()` | `array` | Get all field mappings |

### Static Factory

```php
public static function fromArray(array $config): self
```

Config array format:

```php
[
    'class'  => User::class,
    'table'  => 'custom_users',
    'fields' => [
        'column_name' => 'propertyName',
    ],
]
```

---

## NameConverterInterface

`AsceticSoft\Rowcast\Mapping\NameConverter\NameConverterInterface`

```php
interface NameConverterInterface
{
    public function toPropertyName(string $columnName): string;
    public function toColumnName(string $propertyName): string;
}
```

### Implementations

| Class | Description |
|:------|:------------|
| `SnakeCaseToCamelCaseConverter` | `snake_case` ↔ `camelCase` (default) |
| `NullConverter` | No conversion (pass-through) |

---

## HydratorInterface

`AsceticSoft\Rowcast\Hydration\HydratorInterface`

```php
interface HydratorInterface
{
    public function hydrate(string $className, array $row, ?ResultSetMapping $rsm = null): object;
    public function hydrateAll(string $className, array $rows, ?ResultSetMapping $rsm = null): array;
}
```

### Implementations

| Class | Description |
|:------|:------------|
| `ReflectionHydrator` | Reflection-based hydration with type casting (default) |

---

## TypeCasterInterface

`AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface`

```php
interface TypeCasterInterface
{
    public function supports(string $type): bool;
    public function cast(mixed $value, string $type): mixed;
}
```

### Built-in Implementations

| Class | Supported types |
|:------|:----------------|
| `ScalarTypeCaster` | `int`, `float`, `bool`, `string` |
| `DateTimeTypeCaster` | `DateTime`, `DateTimeImmutable`, `DateTimeInterface` |
| `EnumTypeCaster` | Any `BackedEnum` |

---

## TypeCasterRegistry

`AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry`

| Method | Returns | Description |
|:-------|:--------|:------------|
| `static createDefault()` | `self` | Create with all built-in casters |
| `addCaster(TypeCasterInterface $caster)` | `void` | Register a custom caster |
| `supports(string $type)` | `bool` | Check if a type is supported |
| `cast(mixed $value, string $type)` | `mixed` | Cast a value to the given type |

---

## QueryBuilder

`AsceticSoft\Rowcast\QueryBuilder\QueryBuilder`

### SELECT

| Method | Returns |
|:-------|:--------|
| `select(string ...$columns)` | `self` |
| `from(string $table, ?string $alias = null)` | `self` |
| `leftJoin(string $from, string $table, string $alias, string $condition)` | `self` |
| `innerJoin(string $from, string $table, string $alias, string $condition)` | `self` |
| `rightJoin(string $from, string $table, string $alias, string $condition)` | `self` |
| `where(string $expression)` | `self` |
| `andWhere(string $expression)` | `self` |
| `orWhere(string $expression)` | `self` |
| `groupBy(string ...$columns)` | `self` |
| `having(string $expression)` | `self` |
| `orderBy(string $column, string $direction = 'ASC')` | `self` |
| `addOrderBy(string $column, string $direction = 'ASC')` | `self` |
| `setMaxResults(?int $limit)` | `self` |
| `setFirstResult(?int $offset)` | `self` |

### INSERT

| Method | Returns |
|:-------|:--------|
| `insert(string $table)` | `self` |
| `values(array $values)` | `self` |

### UPDATE

| Method | Returns |
|:-------|:--------|
| `update(string $table)` | `self` |
| `set(string $column, string $value)` | `self` |

### DELETE

| Method | Returns |
|:-------|:--------|
| `delete(string $table)` | `self` |

### Parameters & Execution

| Method | Returns | Description |
|:-------|:--------|:------------|
| `setParameter(string\|int $key, mixed $value)` | `self` | Set a query parameter |
| `getSQL()` | `string` | Get the generated SQL |
| `fetchAllAssociative()` | `array` | Execute and fetch all rows |
| `fetchAssociative()` | `array\|false` | Execute and fetch one row |
| `fetchOne()` | `mixed` | Execute and fetch a scalar |
| `executeQuery()` | `\PDOStatement` | Execute a SELECT |
| `executeStatement()` | `int` | Execute INSERT/UPDATE/DELETE |

---

## QueryType

`AsceticSoft\Rowcast\QueryBuilder\QueryType`

```php
enum QueryType
{
    case Select;
    case Insert;
    case Update;
    case Delete;
}
```

---

## Architecture

```
AsceticSoft\Rowcast\
├── Connection                          # PDO wrapper with convenience methods
├── DataMapper                          # Main DataMapper (CRUD operations)
├── Hydration\
│   ├── HydratorInterface              # Hydrator contract
│   └── ReflectionHydrator             # Reflection-based hydrator
├── Mapping\
│   ├── ResultSetMapping               # Explicit column ↔ property mapping
│   └── NameConverter\
│       ├── NameConverterInterface     # Name converter contract
│       ├── SnakeCaseToCamelCaseConverter  # snake_case ↔ camelCase (default)
│       └── NullConverter              # No conversion (pass-through)
├── QueryBuilder\
│   ├── QueryBuilder                   # Fluent SQL query builder
│   └── QueryType                      # Query type enum
└── TypeCaster\
    ├── TypeCasterInterface            # Type caster contract
    ├── TypeCasterRegistry             # Registry managing multiple casters
    ├── ScalarTypeCaster               # int, float, bool, string
    ├── DateTimeTypeCaster             # DateTime, DateTimeImmutable
    └── EnumTypeCaster                 # BackedEnum
```
