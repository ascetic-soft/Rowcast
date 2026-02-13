---
title: Hydration
layout: default
nav_order: 8
---

# Hydration
{: .no_toc }

How database rows are converted to PHP objects.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Overview

Hydration is the process of converting raw database rows (associative arrays) into typed PHP DTO objects. Rowcast uses a **Reflection-based hydrator** by default that handles this automatically.

---

## ReflectionHydrator (Default)

The built-in `ReflectionHydrator`:

1. Creates a new instance of the DTO class without calling the constructor
2. Maps each column value to the corresponding property
3. Casts values to the declared PHP types using the `TypeCasterRegistry`
4. Sets the property values via Reflection

```php
use AsceticSoft\Rowcast\Hydration\ReflectionHydrator;

$hydrator = new ReflectionHydrator();

// Hydrate a single row
$user = $hydrator->hydrate(User::class, [
    'id' => 1,
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

// Hydrate multiple rows
$users = $hydrator->hydrateAll(User::class, $rows);
```

### Custom TypeCaster registry

```php
use AsceticSoft\Rowcast\TypeCaster\TypeCasterRegistry;

$registry = TypeCasterRegistry::createDefault();
$registry->addCaster(new UuidTypeCaster());

$hydrator = new ReflectionHydrator(typeCaster: $registry);
```

---

## Custom Hydrator

Implement `HydratorInterface` to customize how rows are converted to objects:

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
        // your custom logic
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

### Using a custom hydrator

```php
$mapper = new DataMapper($connection, hydrator: new MyHydrator());
```

---

## HydratorInterface

```php
interface HydratorInterface
{
    /**
     * Hydrate a single row into an object.
     */
    public function hydrate(
        string $className,
        array $row,
        ?ResultSetMapping $rsm = null,
    ): object;

    /**
     * Hydrate multiple rows into an array of objects.
     */
    public function hydrateAll(
        string $className,
        array $rows,
        ?ResultSetMapping $rsm = null,
    ): array;
}
```

{: .tip }
A custom hydrator is useful when you need constructor-based initialization, validation, or integration with a specific framework.
