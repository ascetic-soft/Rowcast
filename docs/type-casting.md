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

## Built-in Type Casters

### ScalarTypeCaster

Handles `int`, `float`, `bool`, and `string` conversions:

```php
// Database "42" → PHP int 42
// Database "3.14" → PHP float 3.14
// Database "1" → PHP bool true
// Database 42 → PHP string "42"
```

### DateTimeTypeCaster

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

### EnumTypeCaster

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

## Custom Type Caster

Implement `TypeCasterInterface` to support additional types:

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

### Registering a custom caster

```php
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;

$registry = TypeCasterRegistry::createDefault();
$registry->addCaster(new UuidTypeCaster());
```

### Using the custom registry

Pass a custom hydrator with the registry to `DataMapper`:

```php
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;
use AsceticSoft\Rowcast\DataMapper;

$hydrator = new ReflectionHydrator(typeCaster: $registry);
$mapper = new DataMapper($connection, hydrator: $hydrator);
```

{: .tip }
The `TypeCasterRegistry::createDefault()` method returns a registry with all built-in casters pre-registered. Use `addCaster()` to extend it with your own.
