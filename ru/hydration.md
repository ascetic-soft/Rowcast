---
title: Гидратация
layout: default
nav_order: 8
parent: Русский
---

# Гидратация
{: .no_toc }

Как строки базы данных преобразуются в PHP-объекты.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Обзор

Гидратация — это процесс преобразования необработанных строк БД (ассоциативных массивов) в типизированные PHP DTO-объекты. Rowcast по умолчанию использует **Reflection-гидратор**, который выполняет это автоматически.

---

## Встроенная гидратация

Гидратация выполняется встроенным `Hydrator`:

1. Создаёт новый экземпляр DTO-класса без вызова конструктора
2. Маппит каждое значение колонки в соответствующее свойство
3. Конвертирует значения к объявленным PHP-типам через `TypeConverterInterface`
4. Устанавливает значения свойств через Reflection

```php
use AsceticSoft\Rowcast\DataMapper;

$mapper = new DataMapper($connection);

// Гидратация одной строки
$user = $mapper->hydrate(User::class, [
    'id' => 1,
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

// Гидратация нескольких строк
$users = $mapper->hydrateAll(User::class, $rows);
```

### С пользовательским маппингом

```php
use AsceticSoft\Rowcast\Mapping;

$mapping = Mapping::explicit(User::class, 'users')
    ->column('usr_email', 'email');

$user = $mapper->hydrate($mapping, [
    'id' => 1,
    'usr_email' => 'alice@example.com',
]);
```

{: .tip }
Тот же пайплайн гидратации используется в `findOne()`, `findAll()` и `iterateAll()`.
