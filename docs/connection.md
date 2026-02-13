---
title: Connection
layout: default
nav_order: 3
---

# Connection
{: .no_toc }

A thin PDO wrapper with convenience methods, transactions, and savepoint support.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Creating a Connection

### From DSN parameters

```php
use AsceticSoft\Rowcast\Connection;

$connection = Connection::create(
    dsn: 'mysql:host=localhost;dbname=app',
    username: 'root',
    password: 'secret',
);
```

### From an existing PDO instance

```php
$pdo = new \PDO('sqlite::memory:');
$connection = new Connection($pdo);
```

{: .note }
`Connection` automatically sets `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION` to ensure all database errors throw exceptions.

---

## Running Raw Queries

### executeQuery — SELECT queries

Returns a `PDOStatement` for reading results:

```php
$stmt = $connection->executeQuery('SELECT * FROM users WHERE id = ?', [1]);
```

### executeStatement — INSERT/UPDATE/DELETE

Returns the number of affected rows:

```php
$affected = $connection->executeStatement(
    'UPDATE users SET name = ? WHERE id = ?',
    ['Alice', 1],
);
```

### Fetch helpers

```php
// Fetch all rows as associative arrays
$rows = $connection->fetchAllAssociative('SELECT * FROM users');

// Fetch a single row
$row = $connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [1]);

// Fetch a single scalar value
$count = $connection->fetchOne('SELECT COUNT(*) FROM users');
```

---

## Transactions

### Manual transaction management

```php
$connection->beginTransaction();
try {
    $connection->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);
    $connection->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
    $connection->commit();
} catch (\Throwable $e) {
    $connection->rollBack();
    throw $e;
}
```

### Automatic transactions (recommended)

```php
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
});
```

The `transactional()` method automatically commits on success and rolls back on exception.

---

## Nested Transactions (Savepoints)

By default, calling `beginTransaction()` inside an active transaction will fail. Enable savepoint-based nesting:

```php
// Via factory
$connection = Connection::create(
    'mysql:host=localhost;dbname=app', 'root', 'secret',
    nestTransactions: true,
);

// Via constructor
$connection = new Connection($pdo, nestTransactions: true);
```

When enabled, inner `beginTransaction()` calls create SQL `SAVEPOINT`s, and `commit()` / `rollBack()` release or roll back to the corresponding savepoint:

```php
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);

    try {
        $conn->transactional(function (Connection $inner) {
            $inner->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
            throw new \RuntimeException('inner failure');
        });
    } catch (\RuntimeException) {
        // Only the inner transaction (Bob) is rolled back.
        // Alice's insert is preserved.
    }
});
// Alice is committed; Bob is not.
```

### Checking nesting level

```php
$level = $connection->getTransactionNestingLevel();
// 0 — no active transaction
```

---

## Query Builder

Create a fluent query builder from the connection:

```php
$qb = $connection->createQueryBuilder();

$rows = $qb->select('id', 'name')
    ->from('users')
    ->where('status = :status')
    ->setParameter('status', 'active')
    ->fetchAllAssociative();
```

See the [Query Builder]({{ '/docs/query-builder.html' | relative_url }}) page for the full reference.

---

## Accessing the underlying PDO

```php
$pdo = $connection->getPdo();
```

{: .warning }
Use direct PDO access sparingly. Prefer using `Connection` methods to maintain consistent error handling and transaction management.
