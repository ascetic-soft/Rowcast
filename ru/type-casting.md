---
title: Приведение типов
layout: default
nav_order: 6
parent: Русский
---

# Приведение типов
{: .no_toc }

Автоматическая конвертация между PHP-типами и значениями базы данных.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Обзор

Rowcast автоматически приводит значения БД к PHP-типам, объявленным в свойствах DTO, и конвертирует PHP-значения обратно в форматы, совместимые с базой данных, при записи.

---

## Чтение (БД → PHP)

| Значение в БД            | Тип PHP-свойства           | Результат                   |
|:-------------------------|:---------------------------|:----------------------------|
| `"42"`                   | `int`                      | `42`                        |
| `"3.14"`                 | `float`                    | `3.14`                      |
| `"1"` / `"0"`            | `bool`                     | `true` / `false`            |
| `42`                     | `string`                   | `"42"`                      |
| `"2025-06-15 10:30:00"`  | `DateTimeImmutable`        | объект `DateTimeImmutable`  |
| `"2025-06-15 10:30:00"`  | `DateTimeInterface`        | объект `DateTimeImmutable`  |
| `"2025-06-15 10:30:00"`  | `DateTime`                 | объект `DateTime`           |
| `"active"`               | `UserStatus` (BackedEnum)  | `UserStatus::Active`        |
| `NULL`                   | `?int`, `?string` и т.д.   | `null`                      |

---

## Запись (PHP → БД)

| PHP-значение         | Значение в БД                   |
|:---------------------|:--------------------------------|
| `true` / `false`     | `1` / `0`                       |
| `DateTimeInterface`  | строка `"Y-m-d H:i:s"`         |
| `BackedEnum`         | Базовое значение (`int`/`string`) |
| `null`               | `NULL`                          |
| Скаляры              | Передаются как есть             |

---

## Встроенные кастеры типов

### ScalarTypeCaster

Обрабатывает конвертацию `int`, `float`, `bool` и `string`:

```php
// БД "42" → PHP int 42
// БД "3.14" → PHP float 3.14
// БД "1" → PHP bool true
// БД 42 → PHP string "42"
```

### DateTimeTypeCaster

Обрабатывает `DateTime`, `DateTimeImmutable` и `DateTimeInterface`:

```php
class Post
{
    public int $id;
    public string $title;
    public DateTimeImmutable $createdAt;
    public DateTime $updatedAt;
}

// Чтение: "2025-06-15 10:30:00" → объект DateTimeImmutable
// Запись: DateTimeImmutable → "2025-06-15 10:30:00"
```

{: .note }
Когда тип свойства — `DateTimeInterface`, значение всегда преобразуется в `DateTimeImmutable`.

### EnumTypeCaster

Обрабатывает любой `BackedEnum`:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';
}

class UserDto
{
    public int $id;
    public Status $status;
    public ?Status $previousStatus;  // nullable enum поддерживается
}

// Чтение: "active" → Status::Active
// Запись: Status::Active → "active"
```

---

## Пользовательский кастер типов

Реализуйте `TypeCasterInterface` для поддержки дополнительных типов:

```php
use AsceticSoft\Rowcast\TypeCaster\TypeCasterInterface;

class UuidTypeCaster implements TypeCasterInterface
{
    public function supports(string $type): bool
    {
        return $type === Uuid::class;
    }

    public function cast(mixed $value, string $type): Uuid
    {
        return new Uuid((string) $value);
    }
}
```

### Регистрация пользовательского кастера

```php
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;

$registry = TypeCasterRegistry::createDefault();
$registry->addCaster(new UuidTypeCaster());
```

### Использование пользовательского реестра

Передайте пользовательский гидратор с реестром в `DataMapper`:

```php
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;
use AsceticSoft\Rowcast\DataMapper;

$hydrator = new ReflectionHydrator(typeCaster: $registry);
$mapper = new DataMapper($connection, hydrator: $hydrator);
```

{: .tip }
Метод `TypeCasterRegistry::createDefault()` возвращает реестр со всеми встроенными кастерами. Используйте `addCaster()` для добавления своих.
