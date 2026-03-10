---
title: DataMapper
layout: default
nav_order: 4
---

# DataMapper
{: .no_toc }

The main class for CRUD operations — maps DTO objects to database rows and back.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Creating a DataMapper

```php
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;

$connection = Connection::create('mysql:host=localhost;dbname=app', 'root', 'secret');
$mapper = new DataMapper($connection);
```

You can optionally provide a custom name converter and/or type converter:

```php
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

$mapper = new DataMapper(
    $connection,
    nameConverter: new SnakeCaseToCamelCase(),
    typeConverter: TypeConverterRegistry::defaults(),
);
```

---

## insert

Inserts a DTO into the database.

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';

$mapper->insert('users', $user);
```

{: .note }
Uninitialized properties are automatically skipped. This is useful for auto-increment primary keys — just don't set `$id` and it won't be included in the INSERT.

### With Mapping (explicit mode)

```php
use AsceticSoft\Rowcast\Mapping;

$mapping = Mapping::explicit(User::class, 'custom_users')
    ->column('usr_nm', 'name')
    ->column('usr_email', 'email');

$mapper->insert($mapping, $user);
```

---

## update

Updates rows matching the WHERE conditions. Returns the number of affected rows.

```php
$user->name = 'Alice Updated';

$affected = $mapper->update('users', $user, ['id' => 1]);
// $affected = 1
```

{: .warning }
WHERE conditions are **required** to prevent accidental mass updates.

### Multiple WHERE conditions

```php
$mapper->update('users', $user, [
    'id' => 1,
    'status' => 'active',
]);
```

---

## delete

Deletes rows matching the WHERE conditions. Returns the number of affected rows.

```php
$affected = $mapper->delete('users', ['id' => 1]);
```

{: .warning }
WHERE conditions are **required** to prevent accidental mass deletes.

---

## findAll

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

---

## findOne

Finds a single row and hydrates it into a DTO object. Returns `null` if no row matches.

```php
$user = $mapper->findOne(User::class, ['id' => 1]);

if ($user === null) {
    // not found
}
```

---

## iterateAll

Same as `findAll`, but returns an iterable (generator) for memory-efficient processing of large result sets.

```php
foreach ($mapper->iterateAll(User::class, ['status' => 'active']) as $user) {
    // Process one user at a time — no full array in memory
    processUser($user);
}
```

Supports the same parameters as `findAll`:

```php
$users = $mapper->iterateAll(
    User::class,
    where: ['status' => 'active'],
    orderBy: ['created_at' => 'DESC'],
    limit: 1000,
);
```

{: .tip }
Use `iterateAll()` instead of `findAll()` when processing thousands of rows to keep memory usage constant.

---

## Auto vs Explicit Mode

### Auto mode

Pass a table name (for writes) or a `class-string` (for reads). Mapping is derived automatically:

| Class name     | Derived table     |
|:---------------|:------------------|
| `User`         | `users`           |
| `UserProfile`  | `user_profiles`   |
| `SimpleUser`   | `simple_users`    |

### Explicit mode

Pass a `Mapping` for full control:

```php
$mapping = Mapping::explicit(User::class, 'custom_users')
    ->column('usr_nm', 'name')
    ->column('usr_email', 'email')
    ->column('id', 'id');

$users = $mapper->findAll($mapping, ['id' => 1]);
$mapper->insert($mapping, $user);
$mapper->update($mapping, $user, ['id' => 1]);
$mapper->delete($mapping, ['id' => 1]);
```

See [Mapping]({{ '/docs/mapping.html' | relative_url }}) for full details.

---

## Advanced WHERE

`DataMapper` forwards `where` arrays to QueryBuilder, so advanced operators are available:

```php
$users = $mapper->findAll(User::class, where: [
    'deleted_at' => null,
    '$or' => [
        ['status' => ['active', 'pending']],
        ['role' => 'admin'],
    ],
    'age >=' => 18,
]);
```

You can also use these operators in `update()`, `delete()`, and `findOne()`.

---

## Accessing the Connection

```php
$connection = $mapper->getConnection();
```
