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

### WHERE как массив

Можно передавать ассоциативные массивы в `where()`, `andWhere()`, `orWhere()`:

```php
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->where(['email' => 'alice@example.com', 'is_active' => 1])
    ->fetchAllAssociative();
// SELECT * FROM users WHERE email = :w_email AND is_active = :w_is_active
```

Поддерживаемые операторы:

```php
->where(['deleted_at' => null])                    // IS NULL
->where(['deleted_at !=' => null])                 // IS NOT NULL
->where(['status' => ['active', 'pending']])       // IN
->where(['status !=' => ['banned']])               // NOT IN
->where(['age >=' => 18, 'score <' => 100])        // сравнения
->where(['name LIKE' => 'A%'])                     // LIKE
->where(['name ILIKE' => 'a%'])                    // ILIKE (PostgreSQL)
->where(['name NOT LIKE' => '%bot%'])              // NOT LIKE
->where(['age BETWEEN' => [18, 65]])               // BETWEEN
```

Примечания:

- Пустой `IN` компилируется в `1 = 0`.
- Пустой `NOT IN` компилируется в `1 = 1`.
- `ILIKE` специфичен для PostgreSQL.

### OR-условия

Два варианта:

```php
// Вариант с whereOr(...)
$rows = $connection->createQueryBuilder()
    ->select('*')
    ->from('users')
    ->whereOr(
        ['status' => 'active', 'age >' => 18],
        ['role' => 'admin'],
    )
    ->fetchAllAssociative();

// Вариант с $or/$and
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

### Доступные методы SELECT

| Метод | Описание |
|:------|:---------|
| `select(...$columns)` | Задать колонки для выборки |
| `from($table, $alias)` | Задать таблицу и опциональный алиас |
| `leftJoin($table, $alias, $condition)` | Добавить LEFT JOIN |
| `innerJoin($table, $alias, $condition)` | Добавить INNER JOIN |
| `rightJoin($table, $alias, $condition)` | Добавить RIGHT JOIN |
| `where($expression)` | Задать условие WHERE (заменяет предыдущее) |
| `andWhere($expression)` | Добавить AND-условие к WHERE |
| `orWhere($expression)` | Добавить OR-условие к WHERE |
| `whereOr(...$groups)` | Заменить WHERE OR-группами |
| `andWhereOr(...$groups)` | Добавить OR-группы через AND |
| `groupBy(...$columns)` | Задать колонки GROUP BY |
| `having($expression)` | Задать условие HAVING |
| `orderBy($column, $direction)` | Задать ORDER BY (заменяет предыдущий) |
| `addOrderBy($column, $direction)` | Добавить дополнительный ORDER BY |
| `setLimit($limit)` | Задать LIMIT |
| `setOffset($offset)` | Задать OFFSET |

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
// например: "SELECT u.id, u.email FROM users u WHERE u.is_active = :active"
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

---

## Выполнение запросов

| Метод | Описание | Для чего |
|:------|:---------|:---------|
| `fetchAllAssociative()` | Возвращает все строки как массивы | SELECT |
| `fetchAssociative()` | Возвращает одну строку | SELECT |
| `fetchOne()` | Возвращает скалярное значение | SELECT COUNT и т.д. |
| `executeQuery()` | Возвращает `PDOStatement` | SELECT |
| `executeStatement()` | Возвращает кол-во затронутых строк | INSERT/UPDATE/DELETE |
