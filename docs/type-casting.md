---
title: Type Casting
layout: default
nav_order: 6
---

# Type Casting
{: .no_toc }

Automatic conversion between PHP types and database values.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Overview

Rowcast automatically casts database values to the PHP types declared on your DTO properties, and converts PHP values back to database-compatible formats on write.

---

## Read (Database → PHP)

| Database value           | PHP property type          | Result                      |
|:-------------------------|:---------------------------|:----------------------------|
| `"42"`                   | `int`                      | `42`                        |
| `"3.14"`                 | `float`                    | `3.14`                      |
| `"1"` / `"0"`            | `bool`                     | `true` / `false`            |
| `42`                     | `string`                   | `"42"`                      |
| `"2025-06-15 10:30:00"`  | `DateTimeImmutable`        | `DateTimeImmutable` object  |
| `"2025-06-15 10:30:00"`  | `DateTimeInterface`        | `DateTimeImmutable` object  |
| `"2025-06-15 10:30:00"`  | `DateTime`                 | `DateTime` object           |
| `"active"`               | `UserStatus` (BackedEnum)  | `UserStatus::Active`        |
| `NULL`                   | `?int`, `?string`, etc.    | `null`                      |

---

## Write (PHP → Database)

| PHP value            | Database value                  |
|:---------------------|:--------------------------------|
| `true` / `false`     | `1` / `0`                       |
| `DateTimeInterface`  | `"Y-m-d H:i:s"` string         |
| `BackedEnum`         | Backing value (`int`/`string`)  |
| `null`               | `NULL`                          |
| Scalars              | Passed through as-is            |

---

## Built-in Type Converters

### ScalarConverter

Handles `int`, `float`, `bool`, and `string` conversions:

```php
// Database "42" → PHP int 42
// Database "3.14" → PHP float 3.14
// Database "1" → PHP bool true
// Database 42 → PHP string "42"
```

### DateTimeConverter

Handles `DateTime`, `DateTimeImmutable`, and `DateTimeInterface`:

```php
class Post
{
    public int $id;
    public string $title;
    public DateTimeImmutable $createdAt;
    public DateTime $updatedAt;
}

// Read: "2025-06-15 10:30:00" → DateTimeImmutable object
// Write: DateTimeImmutable → "2025-06-15 10:30:00"
```

{: .note }
When the property type is `DateTimeInterface`, the value is always resolved to `DateTimeImmutable`.

### EnumConverter

Handles any `BackedEnum`:

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
    public ?Status $previousStatus;  // nullable enums supported
}

// Read: "active" → Status::Active
// Write: Status::Active → "active"
```

---

## Custom Type Converter

Implement `TypeConverterInterface` to support additional types:

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

### Registering a custom converter

```php
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

$registry = TypeConverterRegistry::defaults();
$registry->add(new UuidConverter());
```

### Using the custom registry

Pass the registry directly to `DataMapper`:

```php
use AsceticSoft\Rowcast\DataMapper;

$mapper = new DataMapper($connection, typeConverter: $registry);
```

{: .tip }
The `TypeConverterRegistry::defaults()` method returns a registry with all built-in converters pre-registered. Use `add()` to extend it with your own.
