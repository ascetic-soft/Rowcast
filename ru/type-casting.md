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

## Встроенные конвертеры типов

### ScalarConverter

Обрабатывает конвертацию `int`, `float`, `bool` и `string`:

```php
// БД "42" → PHP int 42
// БД "3.14" → PHP float 3.14
// БД "1" → PHP bool true
// БД 42 → PHP string "42"
```

### DateTimeConverter

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

### EnumConverter

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

## Пользовательский конвертер типов

Реализуйте `TypeConverterInterface` для поддержки дополнительных типов:

```php
use AsceticSoft\Rowcast\TypeConverter\TypeConverterInterface;

class UuidConverter implements TypeConverterInterface
{
    public function supports(string $phpType): bool
    {
        return $phpType === Uuid::class;
    }

    public function toPhp(mixed $value, string $phpType): Uuid
    {
        return new Uuid((string) $value);
    }

    public function toDb(mixed $value): mixed
    {
        return (string) $value;
    }
}
```

### Регистрация пользовательского конвертера

```php
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

$registry = TypeConverterRegistry::defaults();
$registry->add(new UuidConverter());
```

### Использование пользовательского реестра

Передайте реестр напрямую в `DataMapper`:

```php
use AsceticSoft\Rowcast\DataMapper;

$mapper = new DataMapper($connection, typeConverter: $registry);
```

{: .tip }
Метод `TypeConverterRegistry::defaults()` возвращает реестр со всеми встроенными конвертерами. Используйте `add()` для добавления своих.
