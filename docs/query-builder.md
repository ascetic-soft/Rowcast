---
title: Query Builder
layout: default
nav_order: 7
---

# Query Builder
{: .no_toc }

A fluent SQL builder for constructing complex queries.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Creating a Query Builder

```php
$qb = $connection->createQueryBuilder();
```

---

## SELECT

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

### Array-based WHERE

You can pass associative arrays to `where()`, `andWhere()`, and `orWhere()`:

```php
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->where(['email' => 'alice@example.com', 'is_active' => 1])
    ->fetchAllAssociative();
// SELECT * FROM users WHERE email = :w_email AND is_active = :w_is_active
```

Supported operators:

```php
->where(['deleted_at' => null])                    // IS NULL
->where(['deleted_at !=' => null])                 // IS NOT NULL
->where(['status' => ['active', 'pending']])       // IN
->where(['status !=' => ['banned']])               // NOT IN
->where(['age >=' => 18, 'score <' => 100])        // comparisons
->where(['name LIKE' => 'A%'])                     // LIKE
->where(['name ILIKE' => 'a%'])                    // ILIKE (PostgreSQL)
->where(['name NOT LIKE' => '%bot%'])              // NOT LIKE
->where(['age BETWEEN' => [18, 65]])               // BETWEEN
```

Notes:

- Empty `IN` array compiles to `1 = 0`.
- Empty `NOT IN` array compiles to `1 = 1`.
- `ILIKE` is PostgreSQL-specific.

### OR Conditions

Two ways to compose OR groups.

Method-based:

```php
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->whereOr(
        ['status' => 'active', 'age >' => 18],
        ['role' => 'admin'],
    )
    ->fetchAllAssociative();
```

Nested keys (`$or`, `$and`) inside array:

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

---

## Available SELECT clauses

| Method | Description |
|:-------|:------------|
| `select(...$columns)` | Set columns to select |
| `from($table, $alias)` | Set the table and optional alias |
| `leftJoin($table, $alias, $condition)` | Add a LEFT JOIN |
| `innerJoin($table, $alias, $condition)` | Add an INNER JOIN |
| `rightJoin($table, $alias, $condition)` | Add a RIGHT JOIN |
| `where($expression)` | Set the WHERE clause (replaces previous) |
| `andWhere($expression)` | Add an AND condition to WHERE |
| `orWhere($expression)` | Add an OR condition to WHERE |
| `whereOr(...$groups)` | Replace WHERE with OR-composed groups |
| `andWhereOr(...$groups)` | Append OR-composed groups with AND |
| `groupBy(...$columns)` | Set GROUP BY columns |
| `having($expression)` | Set the HAVING clause |
| `orderBy($column, $direction)` | Set ORDER BY (replaces previous) |
| `addOrderBy($column, $direction)` | Add an additional ORDER BY |
| `setLimit($limit)` | Set the LIMIT |
| `setOffset($offset)` | Set the OFFSET |

---

## INSERT / UPDATE / DELETE

```php
$connection->createQueryBuilder()
    ->insert('users')
    ->values([
        'email' => ':email',
        'is_active' => ':is_active',
    ])
    ->setParameter('email', 'alice@example.com')
    ->setParameter('is_active', 1)
    ->executeStatement();

$connection->createQueryBuilder()
    ->update('users')
    ->values(['is_active' => ':is_active'])
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

---

## Parameters

Use named parameters with `:paramName` syntax:

```php
$qb->where('status = :status')
    ->setParameter('status', 'active');
```

Or positional parameters with `?`:

```php
$qb->where('status = ?')
    ->setParameter(0, 'active');
```

---

## Getting the Raw SQL

```php
$sql = $qb->getSQL();
// e.g. "SELECT u.id, u.email FROM users u WHERE u.is_active = :active"
```

---

## UPSERT

```php
$sql = $connection->createQueryBuilder()
    ->upsert('users')
    ->values(['email' => ':email', 'name' => ':name'])
    ->onConflict('email')
    ->doUpdateSet(['name'])
    ->getSQL();
```

UPSERT is dialect-aware (`mysql`, `pgsql`, `sqlite`).

---

## Executing Queries

| Method | Description | Use for |
|:-------|:------------|:--------|
| `fetchAllAssociative()` | Returns all rows as arrays | SELECT |
| `fetchAssociative()` | Returns a single row | SELECT |
| `fetchOne()` | Returns a single scalar | SELECT COUNT, etc. |
| `executeQuery()` | Returns a `PDOStatement` | SELECT |
| `executeStatement()` | Returns affected row count | INSERT/UPDATE/DELETE |
