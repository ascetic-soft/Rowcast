---
title: Построитель запросов
layout: default
nav_order: 7
parent: Русский
---

# Построитель запросов
{: .no_toc }

Fluent SQL-построитель для формирования сложных запросов.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Создание построителя запросов

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

### Доступные методы SELECT

| Метод | Описание |
|:------|:---------|
| `select(...$columns)` | Задать колонки для выборки |
| `from($table, $alias)` | Задать таблицу и опциональный алиас |
| `leftJoin($from, $table, $alias, $condition)` | Добавить LEFT JOIN |
| `innerJoin($from, $table, $alias, $condition)` | Добавить INNER JOIN |
| `rightJoin($from, $table, $alias, $condition)` | Добавить RIGHT JOIN |
| `where($expression)` | Задать условие WHERE (заменяет предыдущее) |
| `andWhere($expression)` | Добавить AND-условие к WHERE |
| `orWhere($expression)` | Добавить OR-условие к WHERE |
| `groupBy(...$columns)` | Задать колонки GROUP BY |
| `having($expression)` | Задать условие HAVING |
| `orderBy($column, $direction)` | Задать ORDER BY (заменяет предыдущий) |
| `addOrderBy($column, $direction)` | Добавить дополнительный ORDER BY |
| `setMaxResults($limit)` | Задать LIMIT |
| `setFirstResult($offset)` | Задать OFFSET |

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

## Параметры

Используйте именованные параметры с синтаксисом `:paramName`:

```php
$qb->where('status = :status')
    ->setParameter('status', 'active');
```

Или позиционные параметры с `?`:

```php
$qb->where('status = ?')
    ->setParameter(0, 'active');
```

---

## Получение сырого SQL

```php
$sql = $qb->getSQL();
// например: "SELECT u.id, u.name FROM users u WHERE u.status = :status"
```

---

## Выполнение запросов

| Метод | Описание | Для чего |
|:------|:---------|:---------|
| `fetchAllAssociative()` | Возвращает все строки как массивы | SELECT |
| `fetchAssociative()` | Возвращает одну строку | SELECT |
| `fetchOne()` | Возвращает скалярное значение | SELECT COUNT и т.д. |
| `executeQuery()` | Возвращает `PDOStatement` | SELECT |
| `executeStatement()` | Возвращает кол-во затронутых строк | INSERT/UPDATE/DELETE |
