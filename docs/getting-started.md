---
title: Getting Started
layout: default
nav_order: 2
---

# Getting Started
{: .no_toc }

Get up and running with Rowcast in under 5 minutes.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Installation

Install Rowcast via Composer:

```bash
composer require ascetic-soft/rowcast
```

**Requirements:**
- PHP >= 8.4
- PDO extension

---

## Your First DTO

### Step 1: Define a DTO class

```php
// src/User.php
class User
{
    public int $id;
    public string $name;
    public string $email;
}
```

No base class, no interfaces, no annotations — just a plain PHP class with typed properties.

### Step 2: Create a connection

```php
use AsceticSoft\Rowcast\Connection;

$connection = Connection::create(
    dsn: 'mysql:host=localhost;dbname=app',
    username: 'root',
    password: 'secret',
);
```

### Step 3: Create a DataMapper and perform CRUD

```php
use AsceticSoft\Rowcast\DataMapper;

$mapper = new DataMapper($connection);

// Insert
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$mapper->insert('users', $user);

// Find
$user = $mapper->findOne(User::class, ['email' => 'alice@example.com']);

// Update
$user->name = 'Alice Updated';
$mapper->update('users', $user, ['id' => $user->id]);

// Delete
$mapper->delete('users', ['id' => $user->id]);
```

---

## How Auto Mode Works

When you pass a `class-string` (e.g. `User::class`) to read methods, Rowcast:

1. Derives the table name from the class name (`User` → `users`, `UserProfile` → `user_profiles`)
2. Executes the SQL query against the derived table
3. Maps each column to a property using `SnakeCaseToCamelCase` (`created_at` → `createdAt`)
4. Casts values to the declared PHP types (`string` → `int`, `"2025-01-01"` → `DateTimeImmutable`, etc.)
5. Returns fully hydrated DTO objects

All of this happens automatically — no configuration required.

{: .note }
Uninitialized properties (e.g. auto-increment `id`) are automatically skipped during `insert()`. This means you don't need to set a default value for your primary key.

---

## Working with Enums

Rowcast supports `BackedEnum` types out of the box:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class UserDto
{
    public int $id;
    public string $name;
    public Status $status;
}

$dto = new UserDto();
$dto->name = 'Alice';
$dto->status = Status::Active;

$mapper->insert('users', $dto);
// Stored as: status = 'active'

$user = $mapper->findOne(UserDto::class, ['id' => 1]);
// $user->status === Status::Active
```

---

## Working with DateTime

`DateTime`, `DateTimeImmutable`, and `DateTimeInterface` properties are automatically handled:

```php
class Post
{
    public int $id;
    public string $title;
    public DateTimeImmutable $createdAt;
}

$post = new Post();
$post->title = 'Hello World';
$post->createdAt = new DateTimeImmutable();

$mapper->insert('posts', $post);
// Stored as: created_at = '2025-06-15 10:30:00'

$found = $mapper->findOne(Post::class, ['id' => 1]);
// $found->createdAt instanceof DateTimeImmutable
```

---

## What's Next?

- [Connection]({{ '/docs/connection.html' | relative_url }}) — PDO wrapper, raw queries, transactions, and savepoints
- [DataMapper]({{ '/docs/datamapper.html' | relative_url }}) — Full CRUD operations reference
- [Mapping]({{ '/docs/mapping.html' | relative_url }}) — Auto mode vs explicit `Mapping`
- [Type Casting]({{ '/docs/type-casting.html' | relative_url }}) — All built-in type converters and custom converters
- [Query Builder]({{ '/docs/query-builder.html' | relative_url }}) — Fluent SQL query builder
- [API Reference]({{ '/docs/api-reference.html' | relative_url }}) — Complete class and method reference
