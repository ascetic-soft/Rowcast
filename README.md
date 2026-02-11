# Rowcast

Lightweight DataMapper over PDO for PHP 8.4+.

Zero external dependencies. Maps database rows to plain DTO objects and back using Reflection, with automatic type casting, naming convention support, and a fluent query builder.

## Requirements

- PHP >= 8.4
- PDO extension

## Installation

```bash
composer require borodulin/rowcast
```

## Quick Start

```php
use Rowcast\Connection;
use Rowcast\DataMapper;

// 1. Create a DTO
class User
{
    public int $id;
    public string $name;
    public string $email;
}

// 2. Connect to the database
$connection = Connection::create('mysql:host=localhost;dbname=app', 'root', 'secret');
$mapper = new DataMapper($connection);

// 3. Insert
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';

$id = $mapper->insert('users', $user);

// 4. Find
$user = $mapper->findOne(User::class, ['id' => 1]);
// User { id: 1, name: "Alice", email: "alice@example.com" }

// 5. Update
$user->name = 'Alice Updated';
$mapper->update('users', $user, ['id' => $user->id]);

// 6. Delete
$mapper->delete('users', ['id' => $user->id]);
```

## Core Concepts

Rowcast operates in two modes:

- **Auto mode** — pass a table name (for writes) or a `class-string` (for reads). Property-to-column mapping is derived automatically via a `NameConverter` (by default `snake_case` columns map to `camelCase` properties).
- **Explicit mode** — pass a `ResultSetMapping` for full control over column-to-property mapping and table name.

### Auto Mode

In auto mode the table name for reads is derived from the class name:

| Class name    | Derived table    |
|---------------|------------------|
| `User`        | `users`          |
| `UserProfile` | `user_profiles`  |
| `SimpleUser`  | `simple_users`   |

Property names are converted using `SnakeCaseToCamelCaseConverter`:

| Column name   | Property name |
|---------------|---------------|
| `created_at`  | `createdAt`   |
| `user_name`   | `userName`    |
| `id`          | `id`          |

### Explicit Mode (ResultSetMapping)

When column names don't follow conventions or the table name differs, use `ResultSetMapping`:

```php
use Rowcast\Mapping\ResultSetMapping;

$rsm = new ResultSetMapping(User::class, table: 'custom_users');
$rsm->addField('usr_nm', 'name')
    ->addField('usr_email', 'email')
    ->addField('id', 'id');

// Insert
$mapper->insert($rsm, $user);

// Find
$user = $mapper->findOne($rsm, ['id' => 1]);

// Update
$mapper->update($rsm, $user, ['id' => 1]);

// Delete
$mapper->delete($rsm, ['id' => 1]);
```

You can also create a `ResultSetMapping` from an array:

```php
$rsm = ResultSetMapping::fromArray([
    'class'  => User::class,
    'table'  => 'custom_users',
    'fields' => [
        'usr_nm'    => 'name',
        'usr_email' => 'email',
    ],
]);
```

## Connection

`Connection` is a thin wrapper around PDO that enforces exception error mode and provides convenience methods.

### Creating a Connection

```php
use Rowcast\Connection;

// From DSN parameters
$connection = Connection::create(
    dsn: 'mysql:host=localhost;dbname=app',
    username: 'root',
    password: 'secret',
);

// From an existing PDO instance
$pdo = new \PDO('sqlite::memory:');
$connection = new Connection($pdo);
```

### Running Raw Queries

```php
// SELECT — returns PDOStatement
$stmt = $connection->executeQuery('SELECT * FROM users WHERE id = ?', [1]);

// INSERT/UPDATE/DELETE — returns affected row count
$affected = $connection->executeStatement(
    'UPDATE users SET name = ? WHERE id = ?',
    ['Alice', 1],
);

// Fetch all rows as associative arrays
$rows = $connection->fetchAllAssociative('SELECT * FROM users');

// Fetch a single row
$row = $connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [1]);

// Fetch a single scalar value
$count = $connection->fetchOne('SELECT COUNT(*) FROM users');
```

### Transactions

```php
// Manual transaction management
$connection->beginTransaction();
try {
    $connection->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);
    $connection->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
    $connection->commit();
} catch (\Throwable $e) {
    $connection->rollBack();
    throw $e;
}

// Automatic transaction (recommended)
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
});
```

## DataMapper

### insert

Inserts a DTO into the database. Uninitialized properties are automatically skipped (useful for auto-increment primary keys).

```php
$user = new User();
$user->name = 'Alice';        // id is not set — will be skipped
$user->email = 'alice@example.com';

$id = $mapper->insert('users', $user);
// $id = "1"
```

### update

Updates rows matching the WHERE conditions. Returns the number of affected rows.

```php
$user->name = 'Alice Updated';

$affected = $mapper->update('users', $user, ['id' => 1]);
// $affected = 1
```

WHERE conditions are required to prevent accidental mass updates.

### delete

Deletes rows matching the WHERE conditions. Returns the number of affected rows.

```php
$affected = $mapper->delete('users', ['id' => 1]);
```

WHERE conditions are required to prevent accidental mass deletes.

### findAll

Finds all rows matching the conditions and hydrates them into DTO objects.

```php
// All users
$users = $mapper->findAll(User::class);

// With conditions
$users = $mapper->findAll(User::class, ['status' => 'active']);

// With ordering
$users = $mapper->findAll(User::class, orderBy: ['name' => 'ASC']);

// With pagination
$users = $mapper->findAll(User::class, limit: 10, offset: 20);

// Combined
$users = $mapper->findAll(
    User::class,
    where: ['status' => 'active'],
    orderBy: ['created_at' => 'DESC'],
    limit: 10,
    offset: 0,
);
```

### findOne

Finds a single row and hydrates it into a DTO object. Returns `null` if no row matches.

```php
$user = $mapper->findOne(User::class, ['id' => 1]);

if ($user === null) {
    // not found
}
```

## Type Casting

Rowcast automatically casts database values to the PHP types declared on your DTO properties, and converts PHP values back to database-compatible formats on write.

### Read (DB to PHP)

| Database value | PHP property type      | Result                |
|----------------|------------------------|-----------------------|
| `"42"`         | `int`                  | `42`                  |
| `"3.14"`       | `float`                | `3.14`                |
| `"1"` / `"0"`  | `bool`                 | `true` / `false`      |
| `42`           | `string`               | `"42"`                |
| `"2025-06-15 10:30:00"` | `DateTimeImmutable` | `DateTimeImmutable` object |
| `"2025-06-15 10:30:00"` | `DateTime`          | `DateTime` object     |
| `"active"`     | `UserStatus` (BackedEnum) | `UserStatus::Active` |
| `NULL`         | `?int`, `?string`, etc. | `null`               |

### Write (PHP to DB)

| PHP value            | Database value             |
|----------------------|----------------------------|
| `true` / `false`     | `1` / `0`                  |
| `DateTimeInterface`  | `"Y-m-d H:i:s"` string    |
| `BackedEnum`         | Backing value (`int`/`string`) |
| `null`               | `NULL`                     |
| Scalars              | Passed through as-is       |

### Built-in Type Casters

- **ScalarTypeCaster** — `int`, `float`, `bool`, `string`
- **DateTimeTypeCaster** — `DateTime`, `DateTimeImmutable`
- **EnumTypeCaster** — any `BackedEnum`

### Custom Type Caster

Implement `TypeCasterInterface` and register it in the registry:

```php
use Rowcast\TypeCaster\TypeCasterInterface;
use Rowcast\TypeCaster\TypeCasterRegistry;

class UuidTypeCaster implements TypeCasterInterface
{
    public function supports(string $type): bool
    {
        return $type === Uuid::class;
    }

    public function cast(mixed $value, string $type): Uuid
    {
        return new Uuid((string) $value);
    }
}

$registry = TypeCasterRegistry::createDefault();
$registry->addCaster(new UuidTypeCaster());
```

Then pass a custom hydrator to `DataMapper`:

```php
use Rowcast\Hydration\ReflectionHydrator;

$hydrator = new ReflectionHydrator(typeCaster: $registry);
$mapper = new DataMapper($connection, hydrator: $hydrator);
```

## Query Builder

`Connection::createQueryBuilder()` returns a fluent query builder for constructing complex SQL queries.

### SELECT

```php
$qb = $connection->createQueryBuilder();

$rows = $qb->select('u.id', 'u.name', 'p.title')
    ->from('users', 'u')
    ->leftJoin('u', 'posts', 'p', 'p.user_id = u.id')
    ->where('u.status = :status')
    ->andWhere('u.created_at > :date')
    ->groupBy('u.id')
    ->having('COUNT(p.id) > :min')
    ->orderBy('u.name', 'ASC')
    ->addOrderBy('u.id', 'DESC')
    ->setMaxResults(10)
    ->setFirstResult(0)
    ->setParameter('status', 'active')
    ->setParameter('date', '2025-01-01')
    ->setParameter('min', 5)
    ->fetchAllAssociative();
```

### INSERT

```php
$qb = $connection->createQueryBuilder();

$qb->insert('users')
    ->values([
        'name'  => ':name',
        'email' => ':email',
    ])
    ->setParameter('name', 'Alice')
    ->setParameter('email', 'alice@example.com')
    ->executeStatement();
```

### UPDATE

```php
$qb = $connection->createQueryBuilder();

$qb->update('users')
    ->set('name', ':name')
    ->where('id = :id')
    ->setParameter('name', 'Alice Updated')
    ->setParameter('id', 1)
    ->executeStatement();
```

### DELETE

```php
$qb = $connection->createQueryBuilder();

$qb->delete('users')
    ->where('id = :id')
    ->setParameter('id', 1)
    ->executeStatement();
```

### Getting the Raw SQL

```php
$sql = $qb->getSQL();
```

## Custom Name Converter

By default, `SnakeCaseToCamelCaseConverter` converts between `snake_case` columns and `camelCase` properties. You can provide a different converter:

```php
use Rowcast\Mapping\NameConverter\NullConverter;

// NullConverter: no conversion, property names used as column names
$mapper = new DataMapper($connection, nameConverter: new NullConverter());
```

Implement `NameConverterInterface` for custom logic:

```php
use Rowcast\Mapping\NameConverter\NameConverterInterface;

class PrefixedConverter implements NameConverterInterface
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
```

## Custom Hydrator

Implement `HydratorInterface` to customize how database rows are converted to objects:

```php
use Rowcast\Hydration\HydratorInterface;
use Rowcast\Mapping\ResultSetMapping;

class MyHydrator implements HydratorInterface
{
    public function hydrate(string $className, array $row, ?ResultSetMapping $rsm = null): object
    {
        // your custom logic
    }

    public function hydrateAll(string $className, array $rows, ?ResultSetMapping $rsm = null): array
    {
        return array_map(
            fn(array $row) => $this->hydrate($className, $row, $rsm),
            $rows,
        );
    }
}

$mapper = new DataMapper($connection, hydrator: new MyHydrator());
```

## Working with Enums

Rowcast supports `BackedEnum` types out of the box:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';
}

class UserDto
{
    public int $id;
    public string $name;
    public Status $status;
    public ?Status $previousStatus;  // nullable enums are supported
}

$dto = new UserDto();
$dto->name = 'Alice';
$dto->status = Status::Active;
$dto->previousStatus = null;

$mapper->insert('users', $dto);
// Stored as: status = 'active', previous_status = NULL

$user = $mapper->findOne(UserDto::class, ['id' => 1]);
// $user->status === Status::Active
// $user->previousStatus === null
```

## Working with DateTime

`DateTime` and `DateTimeImmutable` properties are automatically handled:

```php
class Post
{
    public int $id;
    public string $title;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;
}

$post = new Post();
$post->title = 'Hello World';
$post->createdAt = new DateTimeImmutable();
$post->updatedAt = new DateTimeImmutable();

$mapper->insert('posts', $post);
// Stored as: created_at = '2025-06-15 10:30:00', updated_at = '2025-06-15 10:30:00'

$found = $mapper->findOne(Post::class, ['id' => 1]);
// $found->createdAt instanceof DateTimeImmutable
```

## Architecture

```
Rowcast\
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
│   └── QueryType                      # Query type enum (Select, Insert, Update, Delete)
└── TypeCaster\
    ├── TypeCasterInterface            # Type caster contract
    ├── TypeCasterRegistry             # Registry managing multiple casters
    ├── ScalarTypeCaster               # int, float, bool, string
    ├── DateTimeTypeCaster             # DateTime, DateTimeImmutable
    └── EnumTypeCaster                 # BackedEnum
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

## License

MIT
