---
title: DataMapper
layout: default
nav_order: 4
parent: Русский
---

# DataMapper
{: .no_toc }

Основной класс для CRUD-операций — маппит DTO-объекты в строки базы данных и обратно.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Создание DataMapper

```php
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;

$connection = Connection::create('mysql:host=localhost;dbname=app', 'root', 'secret');
$mapper = new DataMapper($connection);
```

Опционально можно передать пользовательский конвертер имён и/или гидратор:

```php
use AsceticSoft\Rowcast\Mapping\NameConverter\NullConverter;
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;

$mapper = new DataMapper(
    $connection,
    nameConverter: new NullConverter(),
    hydrator: new ReflectionHydrator(),
);
```

---

## insert

Вставляет DTO в базу данных. Возвращает последний вставленный ID.

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';

$id = $mapper->insert('users', $user);
// $id = "1"
```

{: .note }
Неинициализированные свойства автоматически пропускаются. Это удобно для автоинкрементных первичных ключей — просто не устанавливайте `$id`, и он не будет включён в INSERT.

### С ResultSetMapping

```php
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;

$rsm = new ResultSetMapping(User::class, table: 'custom_users');
$rsm->addField('usr_nm', 'name')
    ->addField('usr_email', 'email');

$mapper->insert($rsm, $user);
```

---

## update

Обновляет строки, соответствующие WHERE-условиям. Возвращает количество затронутых строк.

```php
$user->name = 'Alice Updated';

$affected = $mapper->update('users', $user, ['id' => 1]);
// $affected = 1
```

{: .warning }
WHERE-условия **обязательны** для предотвращения случайного массового обновления.

### Несколько WHERE-условий

```php
$mapper->update('users', $user, [
    'id' => 1,
    'status' => 'active',
]);
```

---

## delete

Удаляет строки, соответствующие WHERE-условиям. Возвращает количество затронутых строк.

```php
$affected = $mapper->delete('users', ['id' => 1]);
```

{: .warning }
WHERE-условия **обязательны** для предотвращения случайного массового удаления.

---

## findAll

Находит все строки, соответствующие условиям, и гидратирует их в DTO-объекты.

```php
// Все пользователи
$users = $mapper->findAll(User::class);

// С условиями
$users = $mapper->findAll(User::class, ['status' => 'active']);

// С сортировкой
$users = $mapper->findAll(User::class, orderBy: ['name' => 'ASC']);

// С пагинацией
$users = $mapper->findAll(User::class, limit: 10, offset: 20);

// Комбинированный запрос
$users = $mapper->findAll(
    User::class,
    where: ['status' => 'active'],
    orderBy: ['created_at' => 'DESC'],
    limit: 10,
    offset: 0,
);
```

---

## findOne

Находит одну строку и гидратирует её в DTO-объект. Возвращает `null`, если строка не найдена.

```php
$user = $mapper->findOne(User::class, ['id' => 1]);

if ($user === null) {
    // не найден
}
```

---

## iterateAll

Аналогичен `findAll`, но возвращает iterable (генератор) для эффективной обработки больших наборов данных.

```php
foreach ($mapper->iterateAll(User::class, ['status' => 'active']) as $user) {
    // Обрабатываем по одному пользователю — без полного массива в памяти
    processUser($user);
}
```

Поддерживает те же параметры, что и `findAll`:

```php
$users = $mapper->iterateAll(
    User::class,
    where: ['status' => 'active'],
    orderBy: ['created_at' => 'DESC'],
    limit: 1000,
);
```

{: .tip }
Используйте `iterateAll()` вместо `findAll()` при обработке тысяч строк для поддержания постоянного потребления памяти.

---

## Auto-режим и Explicit-режим

### Auto-режим

Передайте имя таблицы (для записи) или `class-string` (для чтения). Маппинг определяется автоматически:

| Имя класса      | Имя таблицы       |
|:-----------------|:-------------------|
| `User`           | `users`            |
| `UserProfile`    | `user_profiles`    |
| `SimpleUser`     | `simple_users`     |

### Explicit-режим

Передайте `ResultSetMapping` для полного контроля:

```php
$rsm = new ResultSetMapping(User::class, table: 'custom_users');
$rsm->addField('usr_nm', 'name')
    ->addField('usr_email', 'email')
    ->addField('id', 'id');

$users = $mapper->findAll($rsm, ['id' => 1]);
$mapper->insert($rsm, $user);
$mapper->update($rsm, $user, ['id' => 1]);
$mapper->delete($rsm, ['id' => 1]);
```

Подробнее см. [Маппинг]({{ '/ru/mapping.html' | relative_url }}).

---

## Доступ к Connection

```php
$connection = $mapper->getConnection();
```
