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

## ReflectionHydrator (по умолчанию)

Встроенный `ReflectionHydrator`:

1. Создаёт новый экземпляр DTO-класса без вызова конструктора
2. Маппит каждое значение колонки в соответствующее свойство
3. Приводит значения к объявленным PHP-типам через `TypeCasterRegistry`
4. Устанавливает значения свойств через Reflection

```php
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;

$hydrator = new ReflectionHydrator();

// Гидратация одной строки
$user = $hydrator->hydrate(User::class, [
    'id' => 1,
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

// Гидратация нескольких строк
$users = $hydrator->hydrateAll(User::class, $rows);
```

### Пользовательский реестр TypeCaster

```php
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;

$registry = TypeCasterRegistry::createDefault();
$registry->addCaster(new UuidTypeCaster());

$hydrator = new ReflectionHydrator(typeCaster: $registry);
```

---

## Пользовательский гидратор

Реализуйте `HydratorInterface` для настройки преобразования строк в объекты:

```php
use AsceticSoft\Rowcast\Hydration\HydratorInterface;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;

class MyHydrator implements HydratorInterface
{
    public function hydrate(
        string $className,
        array $row,
        ?ResultSetMapping $rsm = null,
    ): object {
        // ваша логика
    }

    public function hydrateAll(
        string $className,
        array $rows,
        ?ResultSetMapping $rsm = null,
    ): array {
        return array_map(
            fn(array $row) => $this->hydrate($className, $row, $rsm),
            $rows,
        );
    }
}
```

### Использование пользовательского гидратора

```php
$mapper = new DataMapper($connection, hydrator: new MyHydrator());
```

---

## HydratorInterface

```php
interface HydratorInterface
{
    /**
     * Гидратировать одну строку в объект.
     */
    public function hydrate(
        string $className,
        array $row,
        ?ResultSetMapping $rsm = null,
    ): object;

    /**
     * Гидратировать несколько строк в массив объектов.
     */
    public function hydrateAll(
        string $className,
        array $rows,
        ?ResultSetMapping $rsm = null,
    ): array;
}
```

{: .tip }
Пользовательский гидратор полезен, когда требуется инициализация через конструктор, валидация или интеграция с конкретным фреймворком.
