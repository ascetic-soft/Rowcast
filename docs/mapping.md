---
title: Mapping
layout: default
nav_order: 5
---

# Mapping
{: .no_toc }

Auto mode with conventions, explicit ResultSetMapping, and custom name converters.
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

By default, `SnakeCaseToCamelCaseConverter` handles the conversion:

| Column name    | Property name |
|:---------------|:--------------|
| `created_at`   | `createdAt`   |
| `user_name`    | `userName`    |
| `id`           | `id`          |

---

## ResultSetMapping (Explicit Mode)

When column names don't follow conventions or the table name differs, use `ResultSetMapping`:

```php
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;

$rsm = new ResultSetMapping(User::class, table: 'custom_users');
$rsm->addField('usr_nm', 'name')
    ->addField('usr_email', 'email')
    ->addField('id', 'id');
```

Use the mapping with any DataMapper operation:

```php
$mapper->insert($rsm, $user);
$user = $mapper->findOne($rsm, ['id' => 1]);
$mapper->update($rsm, $user, ['id' => 1]);
$mapper->delete($rsm, ['id' => 1]);
```

### Creating from an array

```php
$rsm = ResultSetMapping::fromArray([
    'class'  => User::class,
    'table'  => 'custom_users',
    'fields' => [
        'usr_nm'    => 'name',
        'usr_email' => 'email',
    ],
]);
```

---

## Name Converters

Name converters define how property names map to column names and vice versa.

### SnakeCaseToCamelCaseConverter (default)

Converts between `snake_case` columns and `camelCase` properties:

```php
use AsceticSoft\Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;

$converter = new SnakeCaseToCamelCaseConverter();
$converter->toPropertyName('created_at'); // 'createdAt'
$converter->toColumnName('createdAt');    // 'created_at'
```

### NullConverter

No conversion — property names are used as column names directly:

```php
use AsceticSoft\Rowcast\Mapping\NameConverter\NullConverter;

$mapper = new DataMapper($connection, nameConverter: new NullConverter());
```

### Custom Name Converter

Implement `NameConverterInterface` for custom logic:

```php
use AsceticSoft\Rowcast\Mapping\NameConverter\NameConverterInterface;

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
