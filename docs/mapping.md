---
title: Mapping
layout: default
nav_order: 5
---

# Mapping
{: .no_toc }

Auto mode with conventions, explicit `Mapping`, and custom name converters.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Auto Mode

In auto mode, Rowcast derives table names and column mappings automatically using conventions.

### Table name derivation

The class name is converted to a plural snake_case table name:

| Class name     | Derived table     |
|:---------------|:------------------|
| `User`         | `users`           |
| `UserProfile`  | `user_profiles`   |
| `SimpleUser`   | `simple_users`    |

### Property-to-column mapping

By default, `SnakeCaseToCamelCase` handles the conversion:

| Column name    | Property name |
|:---------------|:--------------|
| `created_at`   | `createdAt`   |
| `user_name`    | `userName`    |
| `id`           | `id`          |

---

## Mapping (Explicit Mode)

When column names don't follow conventions or the table name differs, use `Mapping`:

```php
use AsceticSoft\Rowcast\Mapping;

$mapping = Mapping::explicit(User::class, 'custom_users')
    ->column('usr_nm', 'name')
    ->column('usr_email', 'email')
    ->column('id', 'id');
```

Use the mapping with any DataMapper operation:

```php
$mapper->insert($mapping, $user);
$user = $mapper->findOne($mapping, ['id' => 1]);
$mapper->update($mapping, $user, ['id' => 1]);
$mapper->delete($mapping, ['id' => 1]);
```

### Auto-discovery with overrides

```php
$mapping = Mapping::auto(User::class, 'custom_users')
    ->column('usr_email', 'email')
    ->ignore('internalNote');
```

---

## Name Converters

Name converters define how property names map to column names and vice versa.

### SnakeCaseToCamelCase (default)

Converts between `snake_case` columns and `camelCase` properties:

```php
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;

$converter = new SnakeCaseToCamelCase();
$converter->toPropertyName('created_at'); // 'createdAt'
$converter->toColumnName('createdAt');    // 'created_at'
```

### Custom Name Converter

Implement `NameConverterInterface` for custom logic:

```php
use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

class PrefixedConverter implements NameConverterInterface
{
    public function toPropertyName(string $columnName): string
    {
        return lcfirst(str_replace('usr_', '', $columnName));
    }

    public function toColumnName(string $propertyName): string
    {
        return 'usr_' . $propertyName;
    }
}

$mapper = new DataMapper($connection, nameConverter: new PrefixedConverter());
```
