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

A PDO wrapper with query helpers, transaction API (including nested transactions via savepoints), and query-builder factory.

### Factory / constructor

```php
public static function create(
    string $dsn,
    ?string $username = null,
    ?string $password = null,
    array $options = [],
    bool $nestTransactions = false,
): self

public function __construct(\PDO $pdo, bool $nestTransactions = false)
```

### Main methods

| Method | Returns |
|:-------|:--------|
| `executeQuery(string $sql, array $params = [])` | `\PDOStatement` |
| `executeStatement(string $sql, array $params = [])` | `int` |
| `fetchAllAssociative(string $sql, array $params = [])` | `list<array<string, mixed>>` |
| `fetchAssociative(string $sql, array $params = [])` | `array<string, mixed>\|false` |
| `fetchOne(string $sql, array $params = [])` | `mixed` |
| `toIterable(string $sql, array $params = [])` | `iterable<int, array<string, mixed>>` |
| `transactional(callable $callback)` | `mixed` |
| `createQueryBuilder()` | `QueryBuilder` |
| `getDriverName()` | `string` |
| `getPdo()` | `\PDO` |

---

## DataMapper

`AsceticSoft\Rowcast\DataMapper`

### Constructor

```php
public function __construct(
    ConnectionInterface $connection,
    ?NameConverterInterface $nameConverter = null,
    ?TypeConverterInterface $typeConverter = null,
)
```

### Main methods

| Method | Returns |
|:-------|:--------|
| `insert(string\|Mapping $target, object $dto)` | `void` |
| `batchInsert(string\|Mapping $target, array $dtos, ?int $maxBindParameters = null)` | `void` |
| `update(string\|Mapping $target, object $dto, array $where)` | `int` |
| `batchUpdate(string\|Mapping $target, array $dtos, array $identityProperties, ?int $maxBindParameters = null)` | `void` |
| `delete(string\|Mapping $target, array $where)` | `int` |
| `findAll(string\|Mapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null)` | `list<object>` |
| `iterateAll(string\|Mapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null)` | `iterable<int, object>` |
| `findOne(string\|Mapping $target, array $where = [])` | `object\|null` |
| `save(string\|Mapping $target, object $dto, string ...$identityProperties)` | `void` |
| `upsert(string\|Mapping $target, object $dto, string ...$conflictProperties)` | `int` |
| `batchUpsert(string\|Mapping $target, array $dtos, array $conflictProperties, ?int $maxBindParameters = null)` | `void` |
| `hydrate(string\|Mapping $target, array $row)` | `object` |
| `hydrateAll(string\|Mapping $target, array $rows)` | `list<object>` |
| `extract(string\|Mapping $target, object $dto)` | `array<string, mixed>` |
| `getConnection()` | `ConnectionInterface` |

---

## Mapping

`AsceticSoft\Rowcast\Mapping`

### Factories

```php
public static function auto(string $className, string $table): self
public static function explicit(string $className, string $table): self
```

### Main methods

| Method | Returns |
|:-------|:--------|
| `column(string $columnName, string $propertyName)` | `self` |
| `ignore(string ...$properties)` | `self` |
| `getClassName()` | `string` |
| `getTable()` | `string` |
| `isAutoDiscover()` | `bool` |
| `getColumns()` | `array<string, string>` |

---

## NameConverterInterface

`AsceticSoft\Rowcast\NameConverter\NameConverterInterface`

```php
interface NameConverterInterface
{
    public function toPropertyName(string $columnName): string;
    public function toColumnName(string $propertyName): string;
}
```

Default implementation: `SnakeCaseToCamelCase`.

---

## TypeConverterInterface

`AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface`

```php
interface TypeConverterInterface
{
    public function supports(string $phpType): bool;
    public function toPhp(mixed $value, string $phpType): mixed;
    public function toDb(mixed $value): mixed;
}
```

Built-in converters: `ScalarConverter`, `BoolConverter`, `DateTimeConverter`, `JsonConverter`, `EnumConverter`.

---

## TypeConverterRegistry

`AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry`

| Method | Returns |
|:-------|:--------|
| `static defaults()` | `self` |
| `add(TypeConverterInterface $converter)` | `self` |
| `supports(string $phpType)` | `bool` |
| `toPhp(mixed $value, string $phpType)` | `mixed` |
| `toDb(mixed $value)` | `mixed` |

---

## QueryBuilder

`AsceticSoft\Rowcast\QueryBuilder\QueryBuilder`

### SELECT

| Method | Returns |
|:-------|:--------|
| `select(string ...$columns)` | `self` |
| `from(string $table, ?string $alias = null)` | `self` |
| `leftJoin(string $table, string $alias, string $condition)` | `self` |
| `innerJoin(string $table, string $alias, string $condition)` | `self` |
| `rightJoin(string $table, string $alias, string $condition)` | `self` |
| `where(string\|array $expression)` | `self` |
| `andWhere(string\|array $expression)` | `self` |
| `orWhere(string\|array $expression)` | `self` |
| `whereOr(array ...$groups)` | `self` |
| `andWhereOr(array ...$groups)` | `self` |
| `groupBy(string ...$columns)` | `self` |
| `having(string $expression)` | `self` |
| `orderBy(string $column, string $direction = 'ASC')` | `self` |
| `addOrderBy(string $column, string $direction = 'ASC')` | `self` |
| `setLimit(int $limit)` | `self` |
| `setOffset(int $offset)` | `self` |

### INSERT

| Method | Returns |
|:-------|:--------|
| `insert(string $table)` | `self` |
| `values(array $values)` | `self` |
| `upsert(string $table)` | `self` |
| `onConflict(string ...$columns)` | `self` |
| `doUpdateSet(array $columns)` | `self` |

### UPDATE

| Method | Returns |
|:-------|:--------|
| `update(string $table)` | `self` |
| `values(array $values)` | `self` |
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
    case Upsert;
    case Update;
    case Delete;
}
```

---

## Architecture

```
AsceticSoft\Rowcast\
├── Connection                          # PDO wrapper with convenience methods
├── DataMapper                          # Main DataMapper (CRUD + batch + upsert)
├── Hydrator                            # Reflection-based hydrator
├── Extractor                           # DTO -> DB row extraction
├── Mapping                             # Auto/explicit mapping model
├── NameConverter\
│   ├── NameConverterInterface          # Name converter contract
│   └── SnakeCaseToCamelCase            # snake_case ↔ camelCase (default)
├── QueryBuilder\
│   ├── QueryBuilder                   # Fluent SQL query builder
│   └── QueryType                      # Query type enum
└── TypeConverter\
    ├── TypeConverterInterface         # Type converter contract
    ├── TypeConverterRegistry          # Registry managing converters
    ├── ScalarConverter                # int, float, string
    ├── BoolConverter                  # bool <-> 0/1
    ├── DateTimeConverter              # DateTimeInterface
    ├── JsonConverter                  # array <-> JSON
    └── EnumConverter                  # BackedEnum
```
