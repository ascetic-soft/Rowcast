---
title: Справочник API
layout: default
nav_order: 9
parent: Русский
---

# Справочник API
{: .no_toc }

Полный справочник по всем публичным классам и методам.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Connection

`AsceticSoft\Rowcast\Connection`

PDO-обёртка с хелперами для запросов, транзакциями (включая вложенные через savepoint) и фабрикой QueryBuilder.

### Фабрика / конструктор

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

### Основные методы

| Метод | Возвращает |
|:------|:-----------|
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

### Конструктор

```php
public function __construct(
    ConnectionInterface $connection,
    ?NameConverterInterface $nameConverter = null,
    ?TypeConverterInterface $typeConverter = null,
)
```

### Основные методы

| Метод | Возвращает |
|:------|:-----------|
| `insert(string\|Mapping $target, object $dto)` | `string\|false` |
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

### Фабрики

```php
public static function auto(string $className, string $table): self
public static function explicit(string $className, string $table): self
```

### Основные методы

| Метод | Возвращает |
|:------|:-----------|
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

Реализация по умолчанию: `SnakeCaseToCamelCase`.

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

Встроенные конвертеры: `ScalarConverter`, `BoolConverter`, `DateTimeConverter`, `JsonConverter`, `EnumConverter`.

---

## TypeConverterRegistry

`AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry`

| Метод | Возвращает |
|:------|:-----------|
| `static defaults()` | `self` |
| `add(TypeConverterInterface $converter)` | `self` |
| `supports(string $phpType)` | `bool` |
| `toPhp(mixed $value, string $phpType)` | `mixed` |
| `toDb(mixed $value)` | `mixed` |

---

## QueryBuilder

`AsceticSoft\Rowcast\QueryBuilder\QueryBuilder`

### SELECT

| Метод | Возвращает |
|:------|:-----------|
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

| Метод | Возвращает |
|:------|:-----------|
| `insert(string $table)` | `self` |
| `values(array $values)` | `self` |
| `upsert(string $table)` | `self` |
| `onConflict(string ...$columns)` | `self` |
| `doUpdateSet(array $columns)` | `self` |

### UPDATE

| Метод | Возвращает |
|:------|:-----------|
| `update(string $table)` | `self` |
| `set(string $column, string $value)` | `self` |

### DELETE

| Метод | Возвращает |
|:------|:-----------|
| `delete(string $table)` | `self` |

### Параметры и выполнение

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `setParameter(string\|int $key, mixed $value)` | `self` | Задать параметр запроса |
| `getSQL()` | `string` | Получить сгенерированный SQL |
| `fetchAllAssociative()` | `array` | Выполнить и получить все строки |
| `fetchAssociative()` | `array\|false` | Выполнить и получить одну строку |
| `fetchOne()` | `mixed` | Выполнить и получить скаляр |
| `executeQuery()` | `\PDOStatement` | Выполнить SELECT |
| `executeStatement()` | `int` | Выполнить INSERT/UPDATE/DELETE |

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

## Архитектура

```
AsceticSoft\Rowcast\
├── Connection                          # PDO-обёртка с удобными методами
├── DataMapper                          # Основной DataMapper (CRUD + batch + upsert)
├── Hydrator                            # Reflection-гидратор
├── Extractor                           # Извлечение DTO -> строка БД
├── Mapping                             # Модель auto/explicit-маппинга
├── NameConverter\
│   ├── NameConverterInterface          # Контракт конвертера имён
│   └── SnakeCaseToCamelCase            # snake_case ↔ camelCase (по умолч.)
├── QueryBuilder\
│   ├── QueryBuilder                   # Fluent SQL-построитель
│   └── QueryType                      # Enum типа запроса
└── TypeConverter\
    ├── TypeConverterInterface         # Контракт конвертера типов
    ├── TypeConverterRegistry          # Реестр конвертеров
    ├── ScalarConverter                # int, float, string
    ├── BoolConverter                  # bool <-> 0/1
    ├── DateTimeConverter              # DateTimeInterface
    ├── JsonConverter                  # array <-> JSON
    └── EnumConverter                  # BackedEnum
```
