---
title: Connection
layout: default
nav_order: 3
parent: Русский
---

# Connection
{: .no_toc }

Тонкая PDO-обёртка с удобными методами, транзакциями и поддержкой savepoints.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Создание подключения

### Из DSN-параметров

```php
use AsceticSoft\Rowcast\Connection;

$connection = Connection::create(
    dsn: 'mysql:host=localhost;dbname=app',
    username: 'root',
    password: 'secret',
);
```

### Из существующего PDO-экземпляра

```php
$pdo = new \PDO('sqlite::memory:');
$connection = new Connection($pdo);
```

{: .note }
`Connection` автоматически устанавливает `PDO::ATTR_ERRMODE` в `PDO::ERRMODE_EXCEPTION`, чтобы все ошибки базы данных выбрасывали исключения.

---

## Выполнение прямых запросов

### executeQuery — SELECT-запросы

Возвращает `PDOStatement` для чтения результатов:

```php
$stmt = $connection->executeQuery('SELECT * FROM users WHERE id = ?', [1]);
```

### executeStatement — INSERT/UPDATE/DELETE

Возвращает количество затронутых строк:

```php
$affected = $connection->executeStatement(
    'UPDATE users SET name = ? WHERE id = ?',
    ['Alice', 1],
);
```

### Вспомогательные методы выборки

```php
// Получить все строки как ассоциативные массивы
$rows = $connection->fetchAllAssociative('SELECT * FROM users');

// Получить одну строку
$row = $connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [1]);

// Получить скалярное значение
$count = $connection->fetchOne('SELECT COUNT(*) FROM users');
```

---

## Транзакции

### Ручное управление транзакциями

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

### Автоматические транзакции (рекомендуется)

```php
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
});
```

Метод `transactional()` автоматически коммитит при успехе и откатывает при исключении.

---

## Вложенные транзакции (Savepoints)

По умолчанию вызов `beginTransaction()` внутри активной транзакции завершится ошибкой. Включите вложенность на основе savepoints:

```php
// Через фабрику
$connection = Connection::create(
    'mysql:host=localhost;dbname=app', 'root', 'secret',
    nestTransactions: true,
);

// Через конструктор
$connection = new Connection($pdo, nestTransactions: true);
```

При включении внутренние вызовы `beginTransaction()` создают SQL `SAVEPOINT`, а `commit()` / `rollBack()` освобождают или откатывают соответствующий savepoint:

```php
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO users (name) VALUES (?)', ['Alice']);

    try {
        $conn->transactional(function (Connection $inner) {
            $inner->executeStatement('INSERT INTO users (name) VALUES (?)', ['Bob']);
            throw new \RuntimeException('внутренняя ошибка');
        });
    } catch (\RuntimeException) {
        // Откатывается только внутренняя транзакция (Bob).
        // Вставка Alice сохраняется.
    }
});
// Alice закоммичена; Bob — нет.
```

### Проверка уровня вложенности

```php
$level = $connection->getTransactionNestingLevel();
// 0 — нет активной транзакции
```

---

## Построитель запросов

Создание fluent-построителя запросов из подключения:

```php
$qb = $connection->createQueryBuilder();

$rows = $qb->select('id', 'name')
    ->from('users')
    ->where('status = :status')
    ->setParameter('status', 'active')
    ->fetchAllAssociative();
```

Подробнее см. на странице [Построитель запросов]({{ '/ru/query-builder.html' | relative_url }}).

---

## Доступ к PDO

```php
$pdo = $connection->getPdo();
```

{: .warning }
Используйте прямой доступ к PDO с осторожностью. Предпочтительно использовать методы `Connection` для поддержания согласованной обработки ошибок и управления транзакциями.
