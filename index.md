---
title: Home
layout: home
nav_order: 1
---

# Rowcast

{: .fs-9 }

Lightweight DataMapper over PDO for PHP 8.4+ with DTO hydration and type casting.
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/Rowcast/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Rowcast/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Rowcast/graph/badge.svg)](https://codecov.io/gh/ascetic-soft/Rowcast)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast/php)](https://packagist.org/packages/ascetic-soft/rowcast)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast)](https://packagist.org/packages/ascetic-soft/rowcast)

[Get Started]({{ '/docs/getting-started.html' | relative_url }}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[Русский]({{ '/ru/' | relative_url }}){: .btn .btn-outline .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/ascetic-soft/Rowcast){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## What is Rowcast?

Rowcast is a modern, zero-dependency **DataMapper** for PHP 8.4+. It maps database rows to plain DTO objects and back using Reflection, with automatic type casting, naming convention support, and a fluent query builder.

### Key Highlights

- **Zero external dependencies** — Only PHP 8.4 and the PDO extension required
- **Automatic DTO mapping** — Database rows hydrated to typed PHP objects via Reflection
- **Type casting** — Automatic conversion between PHP and database types (`DateTime`, `BackedEnum`, scalars)
- **Two mapping modes** — Auto mode with conventions or Explicit mode with `ResultSetMapping`
- **Fluent query builder** — Build complex SQL queries with a chainable API
- **Snake-to-camel conversion** — `snake_case` columns map to `camelCase` properties by default
- **Nested transactions** — Savepoint-based transaction nesting support
- **PHPStan Level 9** — Fully statically analyzed codebase

---

## Quick Example

```php
use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;

// Define a DTO
class User
{
    public int $id;
    public string $name;
    public string $email;
}

// Connect and create a mapper
$connection = Connection::create('mysql:host=localhost;dbname=app', 'root', 'secret');
$mapper = new DataMapper($connection);

// Insert
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$mapper->insert('users', $user);

// Find
$user = $mapper->findOne(User::class, ['id' => 1]);
```

That's it. A few lines to get a fully working DataMapper with automatic type casting and DTO hydration.

---

## Why Rowcast?

| Feature | Rowcast | Traditional ORMs |
|:--------|:--------|:-----------------|
| Zero external dependencies | Yes | Often many |
| Plain DTO objects (no base class) | Yes | Requires entity base |
| Automatic type casting | Yes | Manual or annotations |
| Convention-based mapping | Yes | Configuration files |
| Fluent query builder | Yes | Yes |
| Nested transactions (savepoints) | Yes | Some |
| PHPStan Level 9 | Yes | Varies |
| Learning curve | Minimal | Steep |

---

## Requirements

- **PHP** >= 8.4
- **PDO** extension

## Installation

```bash
composer require ascetic-soft/rowcast
```

---

## Documentation

<div class="grid-container" markdown="0">
  <div class="grid-item">
    <h3><a href="{{ '/docs/getting-started.html' | relative_url }}">Getting Started</a></h3>
    <p>Installation, first DTO, and basic CRUD in 5 minutes.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/connection.html' | relative_url }}">Connection</a></h3>
    <p>PDO wrapper, raw queries, transactions, and savepoints.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/datamapper.html' | relative_url }}">DataMapper</a></h3>
    <p>Insert, update, delete, findAll, findOne, and iterateAll.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/mapping.html' | relative_url }}">Mapping</a></h3>
    <p>Auto mode, explicit ResultSetMapping, and name converters.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/type-casting.html' | relative_url }}">Type Casting</a></h3>
    <p>Scalars, DateTime, BackedEnum, and custom type casters.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/query-builder.html' | relative_url }}">Query Builder</a></h3>
    <p>Fluent SQL builder for SELECT, INSERT, UPDATE, DELETE.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/hydration.html' | relative_url }}">Hydration</a></h3>
    <p>Reflection-based hydrator and custom hydrator support.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/api-reference.html' | relative_url }}">API Reference</a></h3>
    <p>Complete reference for all public classes and methods.</p>
  </div>
</div>
