---
title: Маппинг
layout: default
nav_order: 5
parent: Русский
---

# Маппинг
{: .no_toc }

Auto-режим с соглашениями, явный `Mapping` и пользовательские конвертеры имён.
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

По умолчанию `SnakeCaseToCamelCase` выполняет конвертацию:

| Имя колонки    | Имя свойства |
|:---------------|:-------------|
| `created_at`   | `createdAt`  |
| `user_name`    | `userName`   |
| `id`           | `id`         |

---

## Mapping (Explicit-режим)

Когда имена колонок не соответствуют соглашениям или имя таблицы отличается, используйте `Mapping`:

```php
use AsceticSoft\Rowcast\Mapping;

$mapping = Mapping::explicit(User::class, 'custom_users')
    ->column('usr_nm', 'name')
    ->column('usr_email', 'email')
    ->column('id', 'id');
```

Используйте маппинг с любой операцией DataMapper:

```php
$mapper->insert($mapping, $user);
$user = $mapper->findOne($mapping, ['id' => 1]);
$mapper->update($mapping, $user, ['id' => 1]);
$mapper->delete($mapping, ['id' => 1]);
```

### Авто-режим с переопределениями

```php
$mapping = Mapping::auto(User::class, 'custom_users')
    ->column('usr_email', 'email')
    ->ignore('internalNote');
```

---

## Конвертеры имён

Конвертеры имён определяют, как имена свойств маппятся в имена колонок и наоборот.

### SnakeCaseToCamelCase (по умолчанию)

Конвертирует между `snake_case`-колонками и `camelCase`-свойствами:

```php
use AsceticSoft\Rowcast\NameConverter\SnakeCaseToCamelCase;

$converter = new SnakeCaseToCamelCase();
$converter->toPropertyName('created_at'); // 'createdAt'
$converter->toColumnName('createdAt');    // 'created_at'
```

### Пользовательский конвертер имён

Реализуйте `NameConverterInterface` для собственной логики:

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
