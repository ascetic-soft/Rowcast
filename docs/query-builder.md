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

### Available SELECT clauses

| Method | Description |
|:-------|:------------|
| `select(...$columns)` | Set columns to select |
| `from($table, $alias)` | Set the table and optional alias |
| `leftJoin($from, $table, $alias, $condition)` | Add a LEFT JOIN |
| `innerJoin($from, $table, $alias, $condition)` | Add an INNER JOIN |
| `rightJoin($from, $table, $alias, $condition)` | Add a RIGHT JOIN |
| `where($expression)` | Set the WHERE clause (replaces previous) |
| `andWhere($expression)` | Add an AND condition to WHERE |
| `orWhere($expression)` | Add an OR condition to WHERE |
| `groupBy(...$columns)` | Set GROUP BY columns |
| `having($expression)` | Set the HAVING clause |
| `orderBy($column, $direction)` | Set ORDER BY (replaces previous) |
| `addOrderBy($column, $direction)` | Add an additional ORDER BY |
| `setMaxResults($limit)` | Set the LIMIT |
| `setFirstResult($offset)` | Set the OFFSET |

---

## INSERT

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

---

## UPDATE

```php
$qb = $connection->createQueryBuilder();

$qb->update('users')
    ->set('name', ':name')
    ->where('id = :id')
    ->setParameter('name', 'Alice Updated')
    ->setParameter('id', 1)
    ->executeStatement();
```

---

## DELETE

```php
$qb = $connection->createQueryBuilder();

$qb->delete('users')
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
// e.g. "SELECT u.id, u.name FROM users u WHERE u.status = :status"
```

---

## Executing Queries

| Method | Description | Use for |
|:-------|:------------|:--------|
| `fetchAllAssociative()` | Returns all rows as arrays | SELECT |
| `fetchAssociative()` | Returns a single row | SELECT |
| `fetchOne()` | Returns a single scalar | SELECT COUNT, etc. |
| `executeQuery()` | Returns a `PDOStatement` | SELECT |
| `executeStatement()` | Returns affected row count | INSERT/UPDATE/DELETE |
