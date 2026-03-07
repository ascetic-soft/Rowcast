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

## Built-in Hydration

Hydration is performed by Rowcast's built-in `Hydrator`:

1. Creates a new instance of the DTO class without calling the constructor
2. Maps each column value to the corresponding property
3. Converts values to declared PHP types using `TypeConverterInterface`
4. Sets the property values via Reflection

```php
use AsceticSoft\Rowcast\DataMapper;

$mapper = new DataMapper($connection);

// Hydrate a single row
$user = $mapper->hydrate(User::class, [
    'id' => 1,
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

// Hydrate multiple rows
$users = $mapper->hydrateAll(User::class, $rows);
```

### With custom mapping

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
The same hydration pipeline is used by `findOne()`, `findAll()`, and `iterateAll()`.
