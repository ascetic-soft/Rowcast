---
title: Главная
layout: default
nav_order: 1
parent: Русский
permalink: /ru/
---

# Rowcast

{: .fs-9 }

Легковесный DataMapper поверх PDO для PHP 8.4+ с гидратацией DTO и приведением типов.
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/Rowcast/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Rowcast/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Rowcast/graph/badge.svg?token=6GZCAEXM6F)](https://codecov.io/gh/ascetic-soft/Rowcast)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast/php)](https://packagist.org/packages/ascetic-soft/rowcast)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)

[Быстрый старт]({{ '/ru/getting-started.html' | relative_url }}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[English]({{ '/' | relative_url }}){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## Что такое Rowcast?

Rowcast — это современный **DataMapper** для PHP 8.4+ без внешних зависимостей. Он маппит строки базы данных в простые DTO-объекты и обратно с помощью Reflection, автоматического приведения типов, соглашений об именовании и fluent-построителя запросов.

### Ключевые особенности

- **Нулевые внешние зависимости** — требуется только PHP 8.4 и расширение PDO
- **Автоматический маппинг DTO** — строки БД гидратируются в типизированные PHP-объекты через Reflection
- **Приведение типов** — автоматическая конвертация между PHP и типами БД (`DateTime`, `BackedEnum`, скаляры)
- **Два режима маппинга** — Auto-режим с соглашениями или Explicit-режим с `ResultSetMapping`
- **Fluent-построитель запросов** — построение сложных SQL-запросов с цепочечным API
- **Конвертация snake-to-camel** — колонки в `snake_case` маппятся в `camelCase`-свойства по умолчанию
- **Вложенные транзакции** — поддержка вложенных транзакций на основе savepoints
- **PHPStan Level 9** — полностью статически проанализированная кодовая база

---

## Быстрый пример

```php
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;

// Определяем DTO
class User
{
    public int $id;
    public string $name;
    public string $email;
}

// Подключаемся и создаём маппер
$connection = Connection::create('mysql:host=localhost;dbname=app', 'root', 'secret');
$mapper = new DataMapper($connection);

// Вставка
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$mapper->insert('users', $user);

// Поиск
$user = $mapper->findOne(User::class, ['id' => 1]);
```

Несколько строк — и полноценный DataMapper с автоматическим приведением типов и гидратацией DTO готов.

---

## Почему Rowcast?

| Возможность | Rowcast | Традиционные ORM |
|:------------|:--------|:-----------------|
| Нулевые внешние зависимости | Да | Часто много |
| Простые DTO-объекты (без базового класса) | Да | Требуют entity-базу |
| Автоматическое приведение типов | Да | Ручное или через аннотации |
| Маппинг на основе соглашений | Да | Конфигурационные файлы |
| Fluent-построитель запросов | Да | Да |
| Вложенные транзакции (savepoints) | Да | Не все |
| PHPStan Level 9 | Да | По-разному |
| Порог вхождения | Минимальный | Высокий |

---

## Требования

- **PHP** >= 8.4
- Расширение **PDO**

## Установка

```bash
composer require ascetic-soft/rowcast
```

---

## Документация

<div class="grid-container" markdown="0">
  <div class="grid-item">
    <h3><a href="{{ '/ru/getting-started.html' | relative_url }}">Быстрый старт</a></h3>
    <p>Установка, первый DTO и базовый CRUD за 5 минут.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/connection.html' | relative_url }}">Connection</a></h3>
    <p>PDO-обёртка, прямые запросы, транзакции и savepoints.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/datamapper.html' | relative_url }}">DataMapper</a></h3>
    <p>Insert, update, delete, findAll, findOne и iterateAll.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/mapping.html' | relative_url }}">Маппинг</a></h3>
    <p>Auto-режим, ResultSetMapping и конвертеры имён.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/type-casting.html' | relative_url }}">Приведение типов</a></h3>
    <p>Скаляры, DateTime, BackedEnum и пользовательские кастеры.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/query-builder.html' | relative_url }}">Построитель запросов</a></h3>
    <p>Fluent SQL-построитель для SELECT, INSERT, UPDATE, DELETE.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/hydration.html' | relative_url }}">Гидратация</a></h3>
    <p>Reflection-гидратор и поддержка пользовательских гидраторов.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/api-reference.html' | relative_url }}">Справочник API</a></h3>
    <p>Полный справочник по всем публичным классам и методам.</p>
  </div>
</div>
