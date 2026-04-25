# Refactoring Plan

## Goal

Refactor the library incrementally around `SOLID`, `DRY`, `KISS`, and `YAGNI` without changing the public API or over-abstracting the codebase.

## Principles

- Keep `DataMapper` as a facade, not a logic sink.
- Move orchestration details into a small number of focused internal classes.
- Prefer the smallest correct extraction over speculative architecture.
- Preserve SQL dialect isolation in `src/QueryBuilder/`.
- Verify broad changes in CI order: `make cs-check`, `make phpstan`, `make test`.

## Backlog

## Status Summary

Completed:
- Extract `ReadOperations`
- Extract `WriteOperations`
- Extract `BatchWriteOperations`
- Reassess and remove `QueryHelper`
- Clean up `Connection` internals
- Normalize guard clauses in write and batch flows

Partially completed:
- Simplify the role of `BulkWriter`
- Centralize property-to-column resolution

Remaining:
- Reconfirm `Mapping` documentation and usage boundaries

### 1. Extract read-path from `DataMapper`

Status:
- Completed

Files:
- `src/DataMapper.php`
- `src/ReadOperations.php`
- `tests/V2DataMapperTest.php`

Move:
- `findAll()`
- `findOne()`
- `iterateAll()`
- `hydrate()`
- `hydrateAll()`
- `buildSelectQuery()`

Intent:
- Reduce `DataMapper` size and responsibility.
- Separate read orchestration from write orchestration.

Risk:
- Low

Done when:
- Public API is unchanged.
- Read behavior and tests remain stable.

### 2. Extract write-path from `DataMapper`

Status:
- Completed

Files:
- `src/DataMapper.php`
- `src/WriteOperations.php`
- `tests/V2DataMapperTest.php`

Move:
- `insert()`
- `update()`
- `delete()`
- `save()`
- `upsert()`

Move related helpers:
- `prepareWrite()`
- `prepareResolvedWrite()`
- `buildIdentityWhere()`
- `resolveColumns()`
- `resolveNonKeyColumns()`

Intent:
- Keep `DataMapper` as a thin facade.
- Isolate write-specific validation and orchestration.

Risk:
- Medium

Done when:
- Write methods preserve current behavior.
- Existing guard conditions and exceptions still make sense.

### 3. Extract batch write operations

Status:
- Completed

Files:
- `src/DataMapper.php`
- `src/BatchWriteOperations.php`
- `src/BulkWriter.php`
- `tests/V2DataMapperTest.php`

Move:
- `batchInsert()`
- `batchUpsert()`
- `batchUpdate()`

Move related helpers:
- `extractAll()`
- `resolveMaxBindParameters()`
- `resolveBatchUpdateColumns()`

Intent:
- Pull batch policies out of `DataMapper`.
- Keep batch validation and dialect decisions close together.

Risk:
- Medium to high

Done when:
- SQLite chunking behavior still passes.
- Batch upsert/update edge cases still pass.

### 4. Simplify the role of `BulkWriter`

Status:
- Partially completed

Files:
- `src/BulkWriter.php`
- `src/BatchWriteOperations.php`
- `tests/V2DataMapperTest.php`

Keep in `BulkWriter`:
- transaction wrapper
- chunk splitting
- statement preparation and execution

Keep out of `BulkWriter`:
- DTO awareness
- mapping decisions
- property-to-column rules
- dialect policy decisions beyond executing prepared batch SQL flow

Intent:
- Make `BulkWriter` a narrow execution component.
- Keep policy separate from mechanics.

Current state:
- Batch policy has been moved out of `DataMapper` into `BatchWriteOperations`.
- `BulkWriter` remains focused on chunked insert and batch update execution.
- No public API changes were made in `BulkWriter`.

Risk:
- Low to medium

Done when:
- `BulkWriter` acts as a batch SQL executor, not an orchestration layer.

### 5. Centralize property-to-column resolution

Status:
- Partially completed

Files:
- `src/TargetResolver.php`
- `src/WriteOperations.php`
- `src/BatchWriteOperations.php`

Work:
- Choose a single owner for property-to-column resolution rules.
- Remove duplicated checks for missing extracted columns where feasible.
- Keep mapping behavior consistent across write and batch flows.

Intent:
- Reduce duplication.
- Prevent rule drift between normal and batch writes.

Current state:
- Property-to-column resolution is now localized to operation classes instead of `DataMapper`.
- `TargetResolver` remains the owner of `resolveColumnName()` and identity WHERE construction.
- There is still duplication between `WriteOperations` and `BatchWriteOperations` for extracted-column validation.

Risk:
- Medium

Done when:
- There is one clear source of truth for property-to-column resolution.

### 6. Reassess `QueryHelper`

Status:
- Completed

Files:
- `src/QueryHelper.php`
- `src/ReadOperations.php`
- `src/WriteOperations.php`

Work:
- Keep it only if it remains genuinely shared.
- Remove it if it becomes a single-use helper after extraction.

Intent:
- Avoid micro-abstractions without real reuse.

Risk:
- Low

Done when:
- `QueryHelper` is either clearly justified or removed.

Result:
- `QueryHelper` was removed and its small logic was inlined into operation classes.

### 7. Clean up `Connection` internals without redesigning the API

Status:
- Completed

Files:
- `src/Connection.php`
- `tests/ConnectionEdgeCasesTest.php`

Work:
- Extract small private methods for nested transaction branches.
- Extract savepoint-name generation.
- Optionally isolate iterable execution path if readability improves.

Intent:
- Improve readability while preserving the current API.
- Avoid premature fragmentation into many services or interfaces.

Risk:
- Medium

Done when:
- Nested transaction behavior is unchanged.
- `toIterable()` behavior is unchanged.

Result:
- Nested transaction branches, savepoint naming, and iterable statement preparation were extracted into private methods.

### 8. Normalize guards and exception style

Status:
- Completed

Files:
- `src/WriteOperations.php`
- `src/BatchWriteOperations.php`
- `src/TargetResolver.php`
- tests covering exception behavior

Work:
- Consolidate guard clauses by scenario.
- Use a consistent style for exception messages.
- Avoid introducing a hierarchy of custom exceptions unless a concrete need appears.

Intent:
- Make invariants easier to read and maintain.

Risk:
- Low

Done when:
- Guard logic is easier to scan.
- Exception messages are consistent.

Result:
- Repeated guards were consolidated into local private methods in write and batch operation classes without changing behavior.

### 9. Keep `Mapping` as a documented setup object

Status:
- Not started

Files:
- `src/Mapping.php`
- documentation if needed

Work:
- Keep mutable builder-style configuration for now.
- Clarify intended usage and reuse expectations.
- Do not switch to immutable API unless a concrete problem appears.

Intent:
- Favor `KISS` and `YAGNI` over an API redesign.

Risk:
- Low

Done when:
- `Mapping` behavior is explicit and unchanged.

## Suggested Execution Order

1. Extract `ReadOperations`
2. Extract `WriteOperations`
3. Extract `BatchWriteOperations`
4. Simplify `BulkWriter`
5. Centralize property-to-column resolution
6. Reassess `QueryHelper`
7. Clean up `Connection`
8. Normalize guards and exception style
9. Reconfirm `Mapping` documentation and usage boundaries

## Current Recommendation

1. Stop here unless there is a concrete need to further reduce duplication between `WriteOperations` and `BatchWriteOperations`.
2. If continuing, do only a small pass on mapping documentation and setup-object expectations.
3. Avoid introducing new shared abstractions unless repeated change pressure appears in future work.

## Risk Summary

- Low: read extraction, helper reassessment, guard normalization, mapping documentation
- Medium: write extraction, column-resolution centralization, connection cleanup
- Medium to high: batch extraction and chunking-sensitive changes

## Success Criteria

- `DataMapper` becomes a thin facade.
- Read, write, and batch paths are separated.
- Property-to-column rules have one clear owner.
- SQL dialect behavior remains isolated in query-builder and dialect classes.
- No speculative interfaces or abstractions are added without concrete need.
- CI passes after each substantial step.
