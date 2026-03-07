# Rowcast

[![CI](https://github.com/ascetic-soft/Rowcast/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Rowcast/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Rowcast/graph/badge.svg?token=6GZCAEXM6F)](https://codecov.io/gh/ascetic-soft/Rowcast)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)
[![Total Downloads](https://img.shields.io/packagist/dt/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast/php)](https://packagist.org/packages/ascetic-soft/rowcast)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)

Lightweight DataMapper over PDO for PHP 8.4+.

Rowcast maps database rows to DTOs and back using reflection, supports explicit/auto mapping, type conversion, and includes a fluent query builder with dialect-aware UPSERT.

**Documentation:** [English](https://ascetic-soft.github.io/Rowcast/) | [Русский](https://ascetic-soft.github.io/Rowcast/ru/)

## Requirements

- PHP >= 8.4
- `ext-pdo`

## Installation

```bash
composer require ascetic-soft/rowcast
```

## Quick Start

```php
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;

class UserDto
{
    public int $id;
    public string $email;
    public bool $isActive;
}

$connection = Connection::create('sqlite::memory:');
$mapper = new DataMapper($connection);

$user = new UserDto();
$user->email = 'alice@example.com';
$user->isActive = true;

$id = $mapper->insert('users', $user);
$found = $mapper->findOne(UserDto::class, ['id' => (int) $id]);
```

## Core Concepts

Rowcast supports two mapping styles:

- **Auto mapping** — pass `class-string` for reads and table name for writes. Names are converted via `NameConverterInterface` (default: `SnakeCaseToCamelCase`).
- **Explicit mapping** — pass `Mapping` to control table name, column/property pairs, and ignored properties.

### Auto Mapping

Table name is derived from DTO class name for reads:

| Class | Table |
|-------|-------|
| `User` | `users` |
| `UserProfile` | `user_profiles` |

Column/property conversion (default):

| Column | Property |
|--------|----------|
| `created_at` | `createdAt` |
| `is_active` | `isActive` |

### Explicit Mapping

```php
use AsceticSoft\Rowcast\Mapping;

$mapping = Mapping::auto(UserDto::class, 'custom_users')
    ->column('usr_email', 'email')
    ->ignore('internalNote');

$user = $mapper->findOne($mapping, ['id' => 1]);
```

Use `Mapping::explicit(...)` when only declared columns must be used:

```php
$mapping = Mapping::explicit(UserDto::class, 'custom_users')
    ->column('id', 'id')
    ->column('usr_email', 'email');
```

## Connection

`Connection` wraps PDO and provides query helpers, transaction API, nested transaction support (savepoints), and query-builder factory.

### Create Connection

```php
use AsceticSoft\Rowcast\Connection;

// From DSN
$connection = Connection::create(
    dsn: 'mysql:host=localhost;dbname=app',
    username: 'root',
    password: 'secret',
    nestTransactions: true,
);

// From existing PDO
$pdo = new \PDO('sqlite::memory:');
$connection = new Connection($pdo, nestTransactions: true);
```

### Raw Queries

```php
$stmt = $connection->executeQuery('SELECT * FROM users WHERE id = ?', [1]);
$affected = $connection->executeStatement('UPDATE users SET email = ? WHERE id = ?', ['a@x.com', 1]);
$rows = $connection->fetchAllAssociative('SELECT * FROM users');
$row = $connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [1]);
$count = $connection->fetchOne('SELECT COUNT(*) FROM users');
```

### Transactions

```php
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO users (email) VALUES (?)', ['alice@example.com']);
});
```

Nested mode creates savepoints for inner transactions.

## DataMapper API

Main methods:

- `insert(string|Mapping $target, object $dto): string|false`
- `update(string|Mapping $target, object $dto, array $where): int`
- `delete(string|Mapping $target, array $where): int`
- `findAll(string|Mapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array`
- `iterateAll(string|Mapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): iterable`
- `findOne(string|Mapping $target, array $where = []): ?object`
- `save(string|Mapping $target, object $dto, string ...$identityProperties): void`
- `upsert(string|Mapping $target, object $dto, string ...$conflictProperties): int`
- `hydrate(...)`, `hydrateAll(...)`, `extract(...)`

### CRUD Example

```php
$dto = new UserDto();
$dto->email = 'alice@example.com';
$dto->isActive = true;

$id = $mapper->insert('users', $dto);
$one = $mapper->findOne(UserDto::class, ['id' => (int) $id]);

$one->isActive = false;
$mapper->update('users', $one, ['id' => $one->id]);
$mapper->delete('users', ['id' => $one->id]);
```

### `save(...)` Example

`save(...)` checks row existence by identity columns, then performs insert or update.

```php
$mapper->save('users', $dto, 'id');
```

### `upsert(...)` Example

```php
$affected = $mapper->upsert('users', $dto, 'email');
```

### Advanced `where` in DataMapper

`DataMapper` passes `where` arrays to the same QueryBuilder condition engine, so advanced operators are available there as well:

```php
$users = $mapper->findAll(UserDto::class, where: [
    'deleted_at' => null,
    '$or' => [
        ['status' => ['active', 'pending']],
        ['role' => 'admin'],
    ],
    'age >=' => 18,
]);
```

## Type Conversion

Rowcast converts DB values to declared PHP property types on hydrate, and PHP values to DB-safe values on extract/write.

Built-in converters:

- `ScalarConverter` (`int`, `float`, `string`)
- `BoolConverter` (`bool` <-> `0/1`)
- `DateTimeConverter` (`DateTimeInterface` <-> formatted UTC string)
- `JsonConverter` (`array` <-> JSON)
- `EnumConverter` (`BackedEnum` <-> backing value)

### Custom Type Converter

Implement `TypeConverterInterface` and pass a custom registry to `DataMapper`:

```php
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

final class UuidConverter implements TypeConverterInterface
{
    public function supports(string $phpType): bool
    {
        return $phpType === Uuid::class;
    }

    public function toPhp(mixed $value, string $phpType): mixed
    {
        return new Uuid((string) $value);
    }

    public function toDb(mixed $value): mixed
    {
        return (string) $value;
    }
}

$converters = TypeConverterRegistry::defaults()->add(new UuidConverter());
$mapper = new DataMapper($connection, typeConverter: $converters);
```

## Custom Name Converter

Implement `NameConverterInterface` and pass it to `DataMapper`:

```php
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

final class PrefixedConverter implements NameConverterInterface
{
    public function toPropertyName(string $columnName): string
    {
        return lcfirst(str_replace('usr_', '', $columnName));
    }

    public function toColumnName(string $propertyName): string
    {
        return 'usr_' . $propertyName;
    }
}

$mapper = new DataMapper($connection, nameConverter: new PrefixedConverter());
```

## Query Builder

`Connection::createQueryBuilder()` provides a fluent SQL builder.

### SELECT

```php
$rows = $connection->createQueryBuilder()
    ->select('u.id', 'u.email')
    ->from('users', 'u')
    ->where('u.is_active = :active')
    ->orderBy('u.id', 'DESC')
    ->setOffset(20)
    ->setLimit(10)
    ->setParameter('active', 1)
    ->fetchAllAssociative();
```

For pagination, use:

- `setOffset(int $offset)` — start row
- `setLimit(int $limit)` — max rows

You can also pass associative arrays to `where()`, `andWhere()`, and `orWhere()`:

```php
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->where(['email' => 'alice@example.com', 'is_active' => 1])
    ->fetchAllAssociative();
// SQL: SELECT * FROM users WHERE email = :w_email AND is_active = :w_is_active
```

`array` predicates are converted to `field = :param` expressions joined by `AND`:

- `where(['a' => 1, 'b' => 2])` -> `a = :w_a AND b = :w_b`
- `andWhere(['a' => 1])` appends another `AND` block
- `orWhere(['a' => 1])` wraps previous predicate: `(prev OR a = :w_a)`

Parameter names are generated automatically and made unique (`:w_id`, `:w_id_1`, ...).

Supported array operators:

```php
// IS NULL / IS NOT NULL
->where(['deleted_at' => null])        // deleted_at IS NULL
->where(['deleted_at !=' => null])     // deleted_at IS NOT NULL

// IN / NOT IN
->where(['status' => ['active', 'pending']])     // status IN (...)
->where(['status !=' => ['banned']])             // status NOT IN (...)
->where(['status IN' => ['active']])             // explicit IN
->where(['status NOT IN' => ['banned']])         // explicit NOT IN

// BackedEnum in WHERE (direct QueryBuilder usage)
->where(['status' => UserStatus::Active])                               // status = :w_status, parameter value: 'active'
->where(['status' => [UserStatus::Active, UserStatus::Inactive]])       // status IN (...), parameters: 'active', 'inactive'

// Comparison operators
->where(['age >' => 18, 'age <=' => 65, 'score !=' => 0])

// LIKE / ILIKE / NOT LIKE / NOT ILIKE
->where(['name LIKE' => '%alice%'])
->where(['name ILIKE' => '%alice%'])             // useful for PostgreSQL
->where(['name NOT LIKE' => '%bot%'])
->where(['name NOT ILIKE' => '%bot%'])           // PostgreSQL only

// BETWEEN
->where(['age BETWEEN' => [18, 65]])
```

Operator reference:

| Input | Example | SQL fragment (shape) |
|---|---|---|
| Equality | `['id' => 10]` | `id = :w_id` |
| `IS NULL` | `['deleted_at' => null]` | `deleted_at IS NULL` |
| `IS NOT NULL` | `['deleted_at !=' => null]` | `deleted_at IS NOT NULL` |
| `IN` (auto) | `['status' => ['active', 'pending']]` | `status IN (:w_status, :w_status_1)` |
| `NOT IN` (auto) | `['status !=' => ['banned']]` | `status NOT IN (:w_status, ...)` |
| `IN` (explicit) | `['status IN' => ['active']]` | `status IN (:w_status)` |
| `NOT IN` (explicit) | `['status NOT IN' => ['banned']]` | `status NOT IN (:w_status)` |
| Comparison | `['age >=' => 18]` | `age >= :w_age` |
| `LIKE` | `['name LIKE' => 'A%']` | `name LIKE :w_name` |
| `ILIKE` | `['name ILIKE' => 'a%']` | `name ILIKE :w_name` |
| `NOT LIKE` | `['name NOT LIKE' => '%bot%']` | `name NOT LIKE :w_name` |
| `NOT ILIKE` | `['name NOT ILIKE' => '%bot%']` | `name NOT ILIKE :w_name` |
| `BETWEEN` | `['age BETWEEN' => [18, 65]]` | `age BETWEEN :w_age AND :w_age_1` |

Notes:

- Empty `IN` array compiles to `1 = 0` (always false).
- Empty `NOT IN` array compiles to `1 = 1` (always true).
- `ILIKE` and `NOT ILIKE` are PostgreSQL-specific.
- `BackedEnum` values are normalized to backing scalar values in `WHERE` parameters (including `IN` / `NOT IN` arrays).

Dialect-specific operator support:

| Dialect | Extra operators over base set |
|---|---|
| PostgreSQL (`pgsql`) | `ILIKE`, `NOT ILIKE` |
| MySQL (`mysql`) | none |
| SQLite (`sqlite`) | none |
| Generic/other drivers | none |

Base set for all dialects: `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`.

### OR Conditions

You can compose OR logic in two ways.

Method-based OR groups:

```php
// (status = 'active' AND age > 18) OR (role = 'admin')
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->whereOr(
        ['status' => 'active', 'age >' => 18],
        ['role' => 'admin'],
    )
    ->fetchAllAssociative();
```

Combine with existing filters:

```php
// deleted_at IS NULL AND ((status = 'active') OR (role = 'admin'))
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->where(['deleted_at' => null])
    ->andWhereOr(['status' => 'active'], ['role' => 'admin'])
    ->fetchAllAssociative();
```

Nested-key style in one array:

```php
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->where([
        'age >' => 18,
        '$or' => [
            ['status' => 'active'],
            ['$and' => [
                ['role' => 'admin'],
                ['verified' => true],
            ]],
        ],
    ])
    ->fetchAllAssociative();
```

OR composition reference:

| Pattern | Example | SQL fragment (shape) |
|---|---|---|
| `whereOr(...groups)` | `->whereOr(['status' => 'active'], ['role' => 'admin'])` | `((status = :w_status) OR (role = :w_role))` |
| `andWhereOr(...groups)` | `->where(['deleted_at' => null])->andWhereOr(['status' => 'active'], ['role' => 'admin'])` | `deleted_at IS NULL AND ((status = :w_status) OR (role = :w_role))` |
| `$or` inside `where([...])` | `->where(['age >' => 18, '$or' => [['status' => 'active'], ['role' => 'admin']]])` | `age > :w_age AND ((status = :w_status) OR (role = :w_role))` |
| `$and` inside `$or` | `->where(['$or' => [['status' => 'active'], ['$and' => [['role' => 'admin'], ['verified' => true]]]]])` | `((status = :w_status) OR ((role = :w_role) AND (verified = :w_verified)))` |
| Mixed operators in OR groups | `->whereOr(['status' => ['active', 'pending'], 'deleted_at' => null], ['name LIKE' => 'A%', 'age BETWEEN' => [18, 65]])` | `((status IN (...) AND deleted_at IS NULL) OR (name LIKE :w_name AND age BETWEEN :w_age AND :w_age_1))` |

### INSERT / UPDATE / DELETE

```php
$connection->createQueryBuilder()
    ->insert('users')
    ->values(['email' => ':email', 'is_active' => ':is_active'])
    ->setParameter('email', 'alice@example.com')
    ->setParameter('is_active', 1)
    ->executeStatement();

$connection->createQueryBuilder()
    ->update('users')
    ->set('is_active', ':is_active')
    ->where('id = :id')
    ->setParameter('is_active', 0)
    ->setParameter('id', 1)
    ->executeStatement();

$connection->createQueryBuilder()
    ->delete('users')
    ->where('id = :id')
    ->setParameter('id', 1)
    ->executeStatement();
```

### UPSERT

```php
$sql = $connection->createQueryBuilder()
    ->upsert('users')
    ->values(['email' => ':email', 'name' => ':name'])
    ->onConflict('email')
    ->doUpdateSet(['name'])
    ->getSQL();
```

`Upsert` is compiled via SQL dialects:

- `MysqlDialect`
- `PostgresDialect`
- `SqliteDialect`
- `GenericDialect` (throws for unsupported UPSERT)

`WHERE` array operator support is also dialect-aware (for example, `ILIKE`/`NOT ILIKE` only for PostgreSQL).

## Architecture

```text
AsceticSoft\Rowcast\
├── ConnectionInterface
├── Connection
├── DataMapper
├── Hydrator
├── Extractor
├── Mapping
├── TargetResolver
├── QueryHelper
├── NameConverter\
│   ├── NameConverterInterface
│   └── SnakeCaseToCamelCase
├── TypeConverter\
│   ├── TypeConverterInterface
│   ├── TypeConverterRegistry
│   ├── ScalarConverter
│   ├── BoolConverter
│   ├── DateTimeConverter
│   ├── JsonConverter
│   └── EnumConverter
└── QueryBuilder\
    ├── QueryBuilder
    ├── QueryType
    ├── Dialect\
    │   ├── DialectInterface
    │   ├── DialectFactory
    │   ├── AbstractStandardDialect
    │   ├── AbstractOnConflictDialect
    │   ├── MysqlDialect
    │   ├── PostgresDialect
    │   ├── SqliteDialect
    │   └── GenericDialect
    └── Compiler\
        ├── SqlCompilerInterface
        ├── SqlFragments
        ├── SelectCompiler
        ├── InsertCompiler
        ├── UpsertCompiler
        ├── UpdateCompiler
        └── DeleteCompiler
```

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## License

MIT
