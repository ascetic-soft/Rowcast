# Improvements Plan

## Goal

This document turns the current architecture and performance review into an execution plan for Rowcast.

The priorities below aim to keep the library small and predictable while improving:

- hydration and extraction throughput;
- write-path efficiency;
- internal separation of responsibilities;
- future extensibility for new SQL features.

## Current State Summary

Rowcast already has a strong baseline:

- small public API;
- clear PDO-based runtime model;
- dialect-specific SQL behavior isolated under `src/QueryBuilder/Dialect/`;
- practical test coverage around SQLite-backed behavior;
- chunk-aware batch insert and upsert support.

The main opportunities are concentrated in a few hot paths and one growing orchestration class:

- repeated reflection and property-map resolution in hydration and extraction;
- extra database round-trips in `DataMapper::save()`;
- increasing responsibility concentration inside `DataMapper`;
- linear converter lookup on hot paths.

## Priorities

## P1: Cache Reflection and Mapping Metadata

### Why

`Hydrator` and `Extractor` rebuild class metadata on every call. This is likely the biggest performance lever in the current codebase because it affects:

- `findOne()`;
- `findAll()`;
- `iterateAll()`;
- `hydrateAll()`;
- all write methods that extract DTO fields.

### Scope

Target files:

- `src/Hydrator.php`
- `src/Extractor.php`
- `src/PropertyMapResolver.php`
- `src/TargetResolver.php`

### Proposed Changes

1. Cache `ReflectionClass` instances by `class-string`.
2. Cache `ReflectionProperty` lookups by `class-string + property name`.
3. Cache resolved `column => property` maps.
4. Cache derived table names in `TargetResolver`.
5. Reuse prepared metadata inside `hydrateAll()` instead of recalculating per row.

### Design Notes

- Keep the cache internal and in-memory only.
- Prefer private arrays over introducing a generic cache abstraction.
- Treat the optimization as an implementation detail, not a public feature.
- If `Mapping` remains mutable, cache keys must assume a mapping instance can change. The safer long-term direction is to make `Mapping` immutable, but that can be a later step.

### Expected Benefits

- Lower CPU overhead for large result sets.
- Better throughput for repeated DTO extraction and hydration.
- Smaller constant cost per row.

### Risks

- Cache invalidation becomes tricky if mutable `Mapping` objects are reused and modified after first use.
- Overengineering the cache layer could add complexity without enough benefit.

### Validation

- Run `make cs-check`
- Run `make phpstan`
- Run `make test`
- Add focused tests around behavior parity for auto and explicit mapping.
- Optionally add a micro-benchmark script outside CI for before/after comparison.

## P2: Reduce Write-Side Round-Trips in `save()`

### Why

`DataMapper::save()` currently performs a read-before-write flow:

1. `SELECT 1 ... LIMIT 1`
2. `INSERT` or `UPDATE`

That means two database round-trips for a common convenience method. It also creates a write-side N+1 pattern when `save()` is called in loops.

### Scope

Target file:

- `src/DataMapper.php`

### Proposed Changes

1. Decide whether `save()` is primarily:
   - a convenience method with intentionally simple behavior; or
   - an optimized identity-based persistence operation.
2. If optimization is desired, introduce a dialect-aware path:
   - use native UPSERT-capable behavior where possible;
   - keep the current check-then-write fallback for unsupported drivers.
3. Update README wording so users understand the performance tradeoff.

### Design Notes

- Keep semantics explicit. `save()` should not quietly gain surprising behavior differences across drivers unless documented.
- Do not remove the current fallback unless all supported drivers can preserve behavior safely.
- If ambiguity remains, document `upsert()` as the preferred performance-oriented API and `save()` as convenience.

### Expected Benefits

- Fewer queries on the write path.
- Better latency on networked databases.
- Cleaner recommendation story for users handling high write volume.

### Risks

- Behavior differences between `save()` and `upsert()` may be subtle around identity columns and update sets.
- A too-smart implementation could make the code harder to reason about than the benefit justifies.

### Validation

- Add tests for current `save()` behavior before changing implementation.
- Add driver-sensitive tests where behavior differs.
- Run the standard CI gate.

## P3: Split Bulk Write Internals Out of `DataMapper`

### Why

`DataMapper` is currently handling:

- target resolution;
- DTO extraction;
- write orchestration;
- chunk size policy;
- batch insert/upsert execution;
- batch update statement preparation;
- dialect-aware decisions.

The class is still understandable, but it is becoming the place where every persistence concern accumulates.

### Scope

Primary file:

- `src/DataMapper.php`

Potential new internal files:

- `src/BulkWriter.php`
- `src/BatchExecutor.php`
- or another minimal internal helper with a narrower name.

### Proposed Changes

1. Extract chunked multi-row insert/upsert execution.
2. Extract batch update SQL preparation and execution.
3. Keep `DataMapper` as the public orchestration layer.
4. Keep new internals private to the package and avoid expanding the public API.

### Design Notes

- Prefer one small internal collaborator over several thin classes.
- Do not split responsibilities so aggressively that the code becomes harder to follow.
- The goal is to reduce concentration of logic, not to create a framework.

### Expected Benefits

- Easier reasoning about batch behavior.
- Lower regression risk when adding new write features.
- Better locality for bind-limit and chunking policy.

### Risks

- Too much extraction can make the codebase more fragmented.
- If done before metadata caching, the performance win may be negligible by itself.

### Validation

- Keep existing test coverage intact.
- Add tests only where extracted logic changes observable behavior.
- Run the standard CI gate.

## P4: Cache Type Converter Resolution

### Why

`TypeConverterRegistry` currently scans all registered converters linearly on each conversion. That cost is small now, but it appears on every hydrate and extract path.

### Scope

Target file:

- `src/TypeConverter/TypeConverterRegistry.php`

### Proposed Changes

1. Cache converter lookup by normalized PHP type for `toPhp()`.
2. Cache converter lookup by runtime type string for `toDb()`.
3. Keep fallback behavior unchanged when no converter matches.

### Design Notes

- This should remain a micro-optimization, not a redesign.
- Implement after metadata caching, since metadata work is likely the larger bottleneck.

### Expected Benefits

- Lower per-field overhead.
- Good synergy with reflection metadata caching.

### Risks

- Minimal, as long as cache entries are reset correctly when new converters are added.

### Validation

- Add tests that `add()` still affects future conversions correctly.
- Run the standard CI gate.

## P5: Clarify `Mapping` Mutability and Long-Term Direction

### Why

`Mapping` behaves like configuration, but is mutable. That is manageable today, but it complicates future caching and makes shared instances easier to misuse.

### Scope

Target file:

- `src/Mapping.php`

### Proposed Changes

Choose one direction:

1. Short term: keep it mutable, but document it as configuration that should not be mutated after use.
2. Long term: make it immutable and have `column()` / `ignore()` return cloned instances.

### Design Notes

- This should be evaluated carefully because it may affect current user expectations.
- If immutability is introduced, treat it as a backward-compatibility-sensitive change.

### Expected Benefits

- Simpler reasoning for future caches.
- Safer reuse across services and tests.

### Risks

- Potentially breaking behavior for users who mutate mapping objects in-place.

### Validation

- Review public API expectations.
- Add tests that pin the intended mutability model.

Current status:

- `Mapping` remains mutable.
- The current guidance is to treat it as a setup-time configuration object and avoid mutating shared instances after reuse.

## P6: Document Trusted SQL Identifier Boundaries

### Why

Values are parameterized safely, but table names, column names, operators, and raw expressions are accepted as trusted developer input. That is a valid design choice for a lightweight query builder, but it should be explicit.

### Scope

Documentation updates:

- `README.md`
- public docs site sources, if maintained separately

### Proposed Changes

1. Document that identifiers and raw SQL fragments are not sanitized.
2. Document that only values are parameter-bound.
3. Optionally describe future support for identifier quoting as a separate feature, not as implicit behavior.

### Expected Benefits

- Clearer safety boundaries.
- Fewer incorrect assumptions by users.

### Risks

- None beyond documentation maintenance.

## Suggested Iteration Plan

## Iteration 1

Focus on the highest-confidence improvements:

1. Add metadata caching for reflection, property maps, and table-name derivation.
2. Optimize `hydrateAll()` to reuse prepared metadata.
3. Add or refine tests to protect hydration and extraction behavior.

Definition of done:

- no public API changes;
- all tests pass;
- measurable reduction in repeated reflection work;
- code remains readable and local.

## Iteration 2

Focus on write-path behavior:

1. Decide the intended contract of `save()`.
2. Optimize or document `save()` accordingly.
3. Add explicit tests for identity-based write behavior.

Definition of done:

- `save()` semantics are documented;
- implementation matches the documented contract;
- no regression in SQLite-backed behavior.

Current status:

- `save()` remains a convenience read-before-write API.
- `upsert()` remains the preferred API when native conflict handling is available and schema constraints support it.

## Iteration 3

Focus on internal maintainability:

1. Extract batch write internals from `DataMapper`.
2. Add converter lookup caching if still justified after profiling.
3. Reassess whether `Mapping` should remain mutable.

Definition of done:

- `DataMapper` is smaller and more focused;
- internal responsibilities are easier to test and extend;
- no unnecessary public surface growth.

## Non-Goals for Now

These ideas may be useful later, but should not come before the priorities above:

- aggressive SQL rewriting for bulk update via `CASE WHEN`;
- automatic identifier quoting across all query builder output;
- a generic caching subsystem;
- feature expansion that increases the public API before internals are stabilized.

## Recommended Verification Strategy

For any substantial implementation work, verify in CI order:

1. `make cs-check`
2. `make phpstan`
3. `make test`

For performance-oriented changes, also validate with a focused local benchmark or repeated test scenario, especially around:

- `hydrateAll()` with many rows;
- `iterateAll()` over large datasets;
- repeated `extract()` on many DTOs;
- repeated `save()` versus `upsert()` on supported drivers.

## Success Criteria

This plan is successful if Rowcast gains the following without losing its current simplicity:

- faster DTO hydration and extraction on repeated operations;
- lower write-path query overhead;
- a slimmer and more maintainable `DataMapper`;
- clearer documented boundaries around convenience APIs and SQL trust assumptions.
