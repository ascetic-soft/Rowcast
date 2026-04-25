# Benchmarks

This repository includes a local benchmark harness for comparing hot-path performance changes over time.

The benchmark is intentionally simple:

- it uses `sqlite::memory:`;
- it focuses on relative comparison before and after changes;
- it is not part of CI;
- it is not intended to simulate production latency.

## Run

Use the default benchmark target:

```bash
make bench
```

Run with custom row and loop counts:

```bash
php benchmarks/rowcast.php 10000 10
```

Arguments:

- first argument: row count
- second argument: loop count

Defaults:

- rows: `5000`
- loops: `5`

## What It Measures

The harness reports timing and rough memory deltas for:

- `extract`
- `hydrate`
- `batchInsert`
- `findAll`
- `iterateAll`
- `batchUpsert`
- `batchUpdate`

## Recommended Scenarios

Use the same machine and similar runtime conditions when comparing results.

### Small Baseline

```bash
php benchmarks/rowcast.php 1000 5
```

Use this when:

- sanity-checking the harness;
- validating that a small refactor did not cause a clear regression;
- checking developer-machine consistency.

### Medium Comparison

```bash
php benchmarks/rowcast.php 5000 5
```

Use this when:

- comparing hydration and extraction improvements;
- checking whether query and mapping overhead changed materially;
- evaluating day-to-day performance work.

### Larger Stress Pass

```bash
php benchmarks/rowcast.php 20000 3
```

Use this when:

- evaluating scaling behavior;
- comparing batch write paths;
- checking whether memory growth becomes visible at larger sizes.

## How To Compare Results

For meaningful before-and-after comparisons:

1. Run the same command before your change.
2. Record the output.
3. Apply the change.
4. Run the same command again.
5. Compare by operation, not just total runtime.

Focus on relative movement in the operation you changed.

Examples:

- if metadata caching was changed, `extract`, `hydrate`, `findAll`, and `iterateAll` matter most;
- if batch SQL execution changed, compare `batchInsert`, `batchUpsert`, and `batchUpdate`;
- if query compilation changed, compare read-heavy paths and batch write preparation cost.

## Interpreting Memory Numbers

The script reports a rough memory delta for each measured block.

Important notes:

- PHP memory reporting is coarse;
- some results may show `0.00 MB` even when allocations happened;
- time measurements are more useful than memory deltas for small runs;
- larger row counts make memory trends easier to notice.

## Caveats

- SQLite in memory is useful for consistency, not for representing remote database behavior.
- Results will vary with CPU governor, JIT settings, loaded extensions, and system load.
- The benchmark does not replace profiling in a real application.
- `save()` is not benchmarked because its value is semantic convenience rather than raw write efficiency.

## Suggested Workflow

For performance-oriented pull requests:

1. Run a medium comparison before the change.
2. Apply the change.
3. Run the same medium comparison again.
4. If the change targets scalability, also run the larger stress pass.
5. Include the command and before/after numbers in the PR description or notes.
