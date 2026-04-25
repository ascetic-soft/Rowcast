<?php

declare(strict_types=1);

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\Rowcast\TypeConverter\TypeConverterRegistry;

require __DIR__ . '/../vendor/autoload.php';

final class BenchUserDto
{
    public int $id;
    public string $email;
    public bool $isActive;

    /** @var list<string> */
    public array $tags;

    public ?\DateTimeImmutable $createdAt;
    public string $status;
}

/**
 * @return array{rows: int, loops: int}
 */
function benchmarkConfig(array $argv): array
{
    $rows = isset($argv[1]) ? max(1, (int) $argv[1]) : 5000;
    $loops = isset($argv[2]) ? max(1, (int) $argv[2]) : 5;

    return ['rows' => $rows, 'loops' => $loops];
}

function createMapper(): DataMapper
{
    $connection = new Connection(new PDO('sqlite::memory:'));
    $connection->executeStatement(
        'CREATE TABLE bench_user_dtos (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL,
            is_active INTEGER NOT NULL,
            tags TEXT NOT NULL,
            created_at TEXT NULL,
            status TEXT NOT NULL
        )',
    );

    return new DataMapper($connection, typeConverter: TypeConverterRegistry::defaults());
}

/**
 * @return list<BenchUserDto>
 */
function createUsers(int $count, int $idOffset = 0): array
{
    $users = [];

    for ($i = 1; $i <= $count; ++$i) {
        $user = new BenchUserDto();
        $user->id = $idOffset + $i;
        $user->email = 'user-' . $user->id . '@example.com';
        $user->isActive = $i % 2 === 0;
        $user->tags = ['bench', 'tag-' . ($i % 10)];
        $user->createdAt = new DateTimeImmutable('2026-03-07 12:00:00+00:00');
        $user->status = $user->isActive ? 'active' : 'inactive';
        $users[] = $user;
    }

    return $users;
}

/**
 * @param callable(): mixed $operation
 * @return array{ms: float, memory_mb: float}
 */
function measure(callable $operation): array
{
    gc_collect_cycles();
    $startMemory = memory_get_usage(true);
    $start = hrtime(true);
    $operation();
    $elapsedNs = hrtime(true) - $start;
    $memoryDelta = memory_get_usage(true) - $startMemory;

    return [
        'ms' => $elapsedNs / 1_000_000,
        'memory_mb' => $memoryDelta / 1024 / 1024,
    ];
}

/**
 * @param list<array{label: string, ms: float, memory_mb: float}> $results
 */
function printResults(int $rows, int $loops, array $results): void
{
    printf("Rowcast benchmark\n");
    printf("rows=%d loops=%d\n\n", $rows, $loops);
    printf("%-24s %12s %14s\n", 'operation', 'time (ms)', 'memory (MB)');
    printf("%-24s %12s %14s\n", str_repeat('-', 24), str_repeat('-', 12), str_repeat('-', 14));

    foreach ($results as $result) {
        printf("%-24s %12.2f %14.2f\n", $result['label'], $result['ms'], $result['memory_mb']);
    }
}

$config = benchmarkConfig($argv);
$rows = $config['rows'];
$loops = $config['loops'];

$results = [];

$results[] = ['label' => 'extract', ...measure(function () use ($rows, $loops): void {
    $mapper = createMapper();
    $users = createUsers($rows);

    for ($i = 0; $i < $loops; ++$i) {
        foreach ($users as $user) {
            $mapper->extract(BenchUserDto::class, $user);
        }
    }
})];

$results[] = ['label' => 'hydrate', ...measure(function () use ($rows, $loops): void {
    $mapper = createMapper();
    $rowsData = [];

    foreach (createUsers($rows) as $user) {
        $rowsData[] = [
            'id' => (string) $user->id,
            'email' => $user->email,
            'is_active' => $user->isActive ? 1 : 0,
            'tags' => json_encode($user->tags, JSON_THROW_ON_ERROR),
            'created_at' => '2026-03-07 12:00:00+00:00',
            'status' => $user->status,
        ];
    }

    for ($i = 0; $i < $loops; ++$i) {
        $mapper->hydrateAll(BenchUserDto::class, $rowsData);
    }
})];

$results[] = ['label' => 'batchInsert', ...measure(function () use ($rows): void {
    $mapper = createMapper();
    $mapper->batchInsert('bench_user_dtos', createUsers($rows));
})];

$results[] = ['label' => 'findAll', ...measure(function () use ($rows, $loops): void {
    $mapper = createMapper();
    $mapper->batchInsert('bench_user_dtos', createUsers($rows));

    for ($i = 0; $i < $loops; ++$i) {
        $mapper->findAll(BenchUserDto::class, orderBy: ['id' => 'ASC']);
    }
})];

$results[] = ['label' => 'iterateAll', ...measure(function () use ($rows, $loops): void {
    $mapper = createMapper();
    $mapper->batchInsert('bench_user_dtos', createUsers($rows));

    for ($i = 0; $i < $loops; ++$i) {
        foreach ($mapper->iterateAll(BenchUserDto::class, orderBy: ['id' => 'ASC']) as $user) {
            unset($user);
        }
    }
})];

$results[] = ['label' => 'batchUpsert', ...measure(function () use ($rows): void {
    $mapper = createMapper();
    $mapper->batchInsert('bench_user_dtos', createUsers($rows));
    $mapper->batchUpsert('bench_user_dtos', createUsers($rows, 0), ['id']);
})];

$results[] = ['label' => 'batchUpdate', ...measure(function () use ($rows): void {
    $mapper = createMapper();
    $mapper->batchInsert('bench_user_dtos', createUsers($rows));

    $updatedUsers = createUsers($rows);
    foreach ($updatedUsers as $user) {
        $user->email = 'updated-' . $user->id . '@example.com';
        $user->status = 'updated';
    }

    $mapper->batchUpdate('bench_user_dtos', $updatedUsers, ['id']);
})];

printResults($rows, $loops, $results);
