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

Тонкая PDO-обёртка с удобными методами и поддержкой транзакций.

### Фабрика

```php
public static function create(
    string $dsn,
    ?string $username = null,
    ?string $password = null,
    array $options = [],
    bool $nestTransactions = false,
): self
```

### Конструктор

```php
public function __construct(\PDO $pdo, bool $nestTransactions = false)
```

### Методы запросов

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `executeQuery(string $sql, array $params = [])` | `\PDOStatement` | Выполнить SELECT-запрос |
| `executeStatement(string $sql, array $params = [])` | `int` | Выполнить INSERT/UPDATE/DELETE, возвращает кол-во строк |
| `fetchAllAssociative(string $sql, array $params = [])` | `array` | Получить все строки как ассоциативные массивы |
| `fetchAssociative(string $sql, array $params = [])` | `array\|false` | Получить одну строку |
| `fetchOne(string $sql, array $params = [])` | `mixed` | Получить скалярное значение |
| `toIterable(string $sql, array $params = [])` | `iterable` | Вернуть результаты как генератор |
| `lastInsertId(?string $name = null)` | `string\|false` | Получить последний вставленный ID |

### Методы транзакций

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `beginTransaction()` | `void` | Начать транзакцию (или savepoint) |
| `commit()` | `void` | Зафиксировать транзакцию (или освободить savepoint) |
| `rollBack()` | `void` | Откатить транзакцию (или до savepoint) |
| `transactional(callable $callback)` | `mixed` | Выполнить в транзакции с авто-коммитом/откатом |
| `getTransactionNestingLevel()` | `int` | Получить текущую глубину вложенности |

### Другие методы

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `createQueryBuilder()` | `QueryBuilder` | Создать новый построитель запросов |
| `getPdo()` | `\PDO` | Получить базовый PDO-экземпляр |

---

## DataMapper

`AsceticSoft\Rowcast\DataMapper`

### Конструктор

```php
public function __construct(
    Connection $connection,
    ?NameConverterInterface $nameConverter = null,
    ?HydratorInterface $hydrator = null,
)
```

### Методы

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `insert(string\|ResultSetMapping $target, object $dto)` | `string\|false` | Вставить DTO, возвращает последний ID |
| `update(string\|ResultSetMapping $target, object $dto, array $where)` | `int` | Обновить строки, возвращает кол-во |
| `delete(string\|ResultSetMapping $target, array $where)` | `int` | Удалить строки, возвращает кол-во |
| `findAll(string\|ResultSetMapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null)` | `array` | Найти все строки как DTO |
| `iterateAll(string\|ResultSetMapping $target, array $where = [], array $orderBy = [], ?int $limit = null, ?int $offset = null)` | `iterable` | Итерировать строки как DTO (генератор) |
| `findOne(string\|ResultSetMapping $target, array $where = [])` | `object\|null` | Найти одну строку как DTO |
| `getConnection()` | `Connection` | Получить подключение |

---

## ResultSetMapping

`AsceticSoft\Rowcast\Mapping\ResultSetMapping`

### Конструктор

```php
public function __construct(string $className, ?string $table = null)
```

### Методы

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `addField(string $column, string $property)` | `self` | Замаппить колонку на свойство |
| `getClassName()` | `string` | Получить имя DTO-класса |
| `getTable()` | `?string` | Получить имя таблицы |
| `getFields()` | `array` | Получить все маппинги полей |

### Статическая фабрика

```php
public static function fromArray(array $config): self
```

Формат массива:

```php
[
    'class'  => User::class,
    'table'  => 'custom_users',
    'fields' => [
        'column_name' => 'propertyName',
    ],
]
```

---

## NameConverterInterface

`AsceticSoft\Rowcast\Mapping\NameConverter\NameConverterInterface`

```php
interface NameConverterInterface
{
    public function toPropertyName(string $columnName): string;
    public function toColumnName(string $propertyName): string;
}
```

### Реализации

| Класс | Описание |
|:------|:---------|
| `SnakeCaseToCamelCaseConverter` | `snake_case` ↔ `camelCase` (по умолчанию) |
| `NullConverter` | Без конвертации (pass-through) |

---

## HydratorInterface

`AsceticSoft\Rowcast\Hydration\HydratorInterface`

```php
interface HydratorInterface
{
    public function hydrate(string $className, array $row, ?ResultSetMapping $rsm = null): object;
    public function hydrateAll(string $className, array $rows, ?ResultSetMapping $rsm = null): array;
}
```

### Реализации

| Класс | Описание |
|:------|:---------|
| `ReflectionHydrator` | Гидратация на основе Reflection с приведением типов (по умолчанию) |

---

## TypeCasterInterface

`AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface`

```php
interface TypeCasterInterface
{
    public function supports(string $type): bool;
    public function cast(mixed $value, string $type): mixed;
}
```

### Встроенные реализации

| Класс | Поддерживаемые типы |
|:------|:--------------------|
| `ScalarTypeCaster` | `int`, `float`, `bool`, `string` |
| `DateTimeTypeCaster` | `DateTime`, `DateTimeImmutable`, `DateTimeInterface` |
| `EnumTypeCaster` | Любой `BackedEnum` |

---

## TypeCasterRegistry

`AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry`

| Метод | Возвращает | Описание |
|:------|:-----------|:---------|
| `static createDefault()` | `self` | Создать со всеми встроенными кастерами |
| `addCaster(TypeCasterInterface $caster)` | `void` | Зарегистрировать пользовательский кастер |
| `supports(string $type)` | `bool` | Проверить поддержку типа |
| `cast(mixed $value, string $type)` | `mixed` | Привести значение к указанному типу |

---

## QueryBuilder

`AsceticSoft\Rowcast\QueryBuilder\QueryBuilder`

### SELECT

| Метод | Возвращает |
|:------|:-----------|
| `select(string ...$columns)` | `self` |
| `from(string $table, ?string $alias = null)` | `self` |
| `leftJoin(string $from, string $table, string $alias, string $condition)` | `self` |
| `innerJoin(string $from, string $table, string $alias, string $condition)` | `self` |
| `rightJoin(string $from, string $table, string $alias, string $condition)` | `self` |
| `where(string $expression)` | `self` |
| `andWhere(string $expression)` | `self` |
| `orWhere(string $expression)` | `self` |
| `groupBy(string ...$columns)` | `self` |
| `having(string $expression)` | `self` |
| `orderBy(string $column, string $direction = 'ASC')` | `self` |
| `addOrderBy(string $column, string $direction = 'ASC')` | `self` |
| `setMaxResults(?int $limit)` | `self` |
| `setFirstResult(?int $offset)` | `self` |

### INSERT

| Метод | Возвращает |
|:------|:-----------|
| `insert(string $table)` | `self` |
| `values(array $values)` | `self` |

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
    case Update;
    case Delete;
}
```

---

## Архитектура

```
AsceticSoft\Rowcast\
├── Connection                          # PDO-обёртка с удобными методами
├── DataMapper                          # Основной DataMapper (CRUD-операции)
├── Hydration\
│   ├── HydratorInterface              # Контракт гидратора
│   └── ReflectionHydrator             # Гидратор на основе Reflection
├── Mapping\
│   ├── ResultSetMapping               # Явный маппинг колонок ↔ свойств
│   └── NameConverter\
│       ├── NameConverterInterface     # Контракт конвертера имён
│       ├── SnakeCaseToCamelCaseConverter  # snake_case ↔ camelCase (по умолч.)
│       └── NullConverter              # Без конвертации (pass-through)
├── QueryBuilder\
│   ├── QueryBuilder                   # Fluent SQL-построитель
│   └── QueryType                      # Enum типа запроса
└── TypeCaster\
    ├── TypeCasterInterface            # Контракт кастера типов
    ├── TypeCasterRegistry             # Реестр, управляющий кастерами
    ├── ScalarTypeCaster               # int, float, bool, string
    ├── DateTimeTypeCaster             # DateTime, DateTimeImmutable
    └── EnumTypeCaster                 # BackedEnum
```
