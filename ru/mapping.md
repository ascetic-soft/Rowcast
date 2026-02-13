---
title: Маппинг
layout: default
nav_order: 5
parent: Русский
---

# Маппинг
{: .no_toc }

Auto-режим с соглашениями, явный ResultSetMapping и пользовательские конвертеры имён.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Auto-режим

В auto-режиме Rowcast определяет имена таблиц и маппинг колонок автоматически, используя соглашения.

### Определение имени таблицы

Имя класса конвертируется в имя таблицы во множественном числе в snake_case:

| Имя класса      | Имя таблицы       |
|:-----------------|:-------------------|
| `User`           | `users`            |
| `UserProfile`    | `user_profiles`    |
| `SimpleUser`     | `simple_users`     |

### Маппинг свойств-колонок

По умолчанию `SnakeCaseToCamelCaseConverter` выполняет конвертацию:

| Имя колонки    | Имя свойства |
|:---------------|:-------------|
| `created_at`   | `createdAt`  |
| `user_name`    | `userName`   |
| `id`           | `id`         |

---

## ResultSetMapping (Explicit-режим)

Когда имена колонок не соответствуют соглашениям или имя таблицы отличается, используйте `ResultSetMapping`:

```php
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;

$rsm = new ResultSetMapping(User::class, table: 'custom_users');
$rsm->addField('usr_nm', 'name')
    ->addField('usr_email', 'email')
    ->addField('id', 'id');
```

Используйте маппинг с любой операцией DataMapper:

```php
$mapper->insert($rsm, $user);
$user = $mapper->findOne($rsm, ['id' => 1]);
$mapper->update($rsm, $user, ['id' => 1]);
$mapper->delete($rsm, ['id' => 1]);
```

### Создание из массива

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

## Конвертеры имён

Конвертеры имён определяют, как имена свойств маппятся в имена колонок и наоборот.

### SnakeCaseToCamelCaseConverter (по умолчанию)

Конвертирует между `snake_case`-колонками и `camelCase`-свойствами:

```php
use AsceticSoft\Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;

$converter = new SnakeCaseToCamelCaseConverter();
$converter->toPropertyName('created_at'); // 'createdAt'
$converter->toColumnName('createdAt');    // 'created_at'
```

### NullConverter

Без конвертации — имена свойств используются как имена колонок:

```php
use AsceticSoft\Rowcast\Mapping\NameConverter\NullConverter;

$mapper = new DataMapper($connection, nameConverter: new NullConverter());
```

### Пользовательский конвертер имён

Реализуйте `NameConverterInterface` для собственной логики:

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
