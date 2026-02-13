---
title: Быстрый старт
layout: default
nav_order: 2
parent: Русский
---

# Быстрый старт
{: .no_toc }

Начните работу с Rowcast за 5 минут.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Установка

Установите Rowcast через Composer:

```bash
composer require ascetic-soft/rowcast
```

**Требования:**
- PHP >= 8.4
- Расширение PDO

---

## Ваш первый DTO

### Шаг 1: Определите DTO-класс

```php
// src/User.php
class User
{
    public int $id;
    public string $name;
    public string $email;
}
```

Не нужен базовый класс, интерфейсы или аннотации — просто обычный PHP-класс с типизированными свойствами.

### Шаг 2: Создайте подключение

```php
use AsceticSoft\Rowcast\Connection;

$connection = Connection::create(
    dsn: 'mysql:host=localhost;dbname=app',
    username: 'root',
    password: 'secret',
);
```

### Шаг 3: Создайте DataMapper и выполните CRUD-операции

```php
use AsceticSoft\Rowcast\DataMapper;

$mapper = new DataMapper($connection);

// Вставка
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$id = $mapper->insert('users', $user);

// Поиск
$user = $mapper->findOne(User::class, ['id' => 1]);

// Обновление
$user->name = 'Alice Updated';
$mapper->update('users', $user, ['id' => $user->id]);

// Удаление
$mapper->delete('users', ['id' => $user->id]);
```

---

## Как работает Auto-режим

Когда вы передаёте `class-string` (например, `User::class`) в методы чтения, Rowcast:

1. Определяет имя таблицы из имени класса (`User` → `users`, `UserProfile` → `user_profiles`)
2. Выполняет SQL-запрос к полученной таблице
3. Маппит каждую колонку в свойство через `SnakeCaseToCamelCaseConverter` (`created_at` → `createdAt`)
4. Приводит значения к объявленным PHP-типам (`string` → `int`, `"2025-01-01"` → `DateTimeImmutable` и т.д.)
5. Возвращает полностью гидратированные DTO-объекты

Всё это происходит автоматически — конфигурация не требуется.

{: .note }
Неинициализированные свойства (например, автоинкрементный `id`) автоматически пропускаются при `insert()`. Это значит, что вам не нужно задавать значение по умолчанию для первичного ключа.

---

## Работа с Enum

Rowcast поддерживает `BackedEnum`-типы из коробки:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class UserDto
{
    public int $id;
    public string $name;
    public Status $status;
}

$dto = new UserDto();
$dto->name = 'Alice';
$dto->status = Status::Active;

$mapper->insert('users', $dto);
// Сохранено как: status = 'active'

$user = $mapper->findOne(UserDto::class, ['id' => 1]);
// $user->status === Status::Active
```

---

## Работа с DateTime

Свойства типов `DateTime`, `DateTimeImmutable` и `DateTimeInterface` обрабатываются автоматически:

```php
class Post
{
    public int $id;
    public string $title;
    public DateTimeImmutable $createdAt;
}

$post = new Post();
$post->title = 'Hello World';
$post->createdAt = new DateTimeImmutable();

$mapper->insert('posts', $post);
// Сохранено как: created_at = '2025-06-15 10:30:00'

$found = $mapper->findOne(Post::class, ['id' => 1]);
// $found->createdAt instanceof DateTimeImmutable
```

---

## Что дальше?

- [Connection]({{ '/ru/connection.html' | relative_url }}) — PDO-обёртка, прямые запросы, транзакции и savepoints
- [DataMapper]({{ '/ru/datamapper.html' | relative_url }}) — Полный справочник CRUD-операций
- [Маппинг]({{ '/ru/mapping.html' | relative_url }}) — Auto-режим и `ResultSetMapping`
- [Приведение типов]({{ '/ru/type-casting.html' | relative_url }}) — Встроенные и пользовательские кастеры типов
- [Построитель запросов]({{ '/ru/query-builder.html' | relative_url }}) — Fluent SQL-построитель запросов
- [Справочник API]({{ '/ru/api-reference.html' | relative_url }}) — Полный справочник классов и методов
