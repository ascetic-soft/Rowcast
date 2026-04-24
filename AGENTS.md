# Rowcast Agent Notes

## Runtime + Scope
- This is a single-package PHP library, not a workspace/monorepo.
- Requires PHP 8.4+ and `ext-pdo`.
- Main public surfaces live under `src/Connection.php`, `src/DataMapper.php`, `src/Mapping.php`, and `src/QueryBuilder/`.

## Commands
- Install deps: `make install` or `composer install`
- Run the standard local gate in CI order: `make ci`
- Individual checks:
  - `make cs-check`
  - `make phpstan`
  - `make test`
- Auto-fix style: `make cs-fix`
- Run one test file: `vendor/bin/phpunit tests/V2DataMapperTest.php`
- Run one test method: `vendor/bin/phpunit tests/V2DataMapperTest.php --filter testBatchUpsertSupportsChunkingOnSqlite`

## Verification Order
- Match CI order when verifying broad changes: `cs-check -> phpstan -> test`
- The pre-commit hook is not equivalent to CI: it runs `php-cs-fixer fix`, stages updates for already tracked files with `git add -u`, then runs PHPStan. It does not run PHPUnit.

## Code Layout
- `DataMapper` is the orchestration layer for hydrate/extract/query flow; it delegates naming, mapping, extraction, hydration, and WHERE-building rather than implementing those rules inline.
- `Connection` is the PDO wrapper and the factory for `QueryBuilder`; query-builder behavior should usually be changed in `src/QueryBuilder/`, not by adding SQL assembly to `Connection`.
- SQL dialect differences are centralized in `src/QueryBuilder/Dialect/`. `DialectFactory` maps only `mysql`, `pgsql`, and `sqlite`; everything else falls back to `GenericDialect`.

## Repo-Specific Behavior
- Passing a DTO class-string target to `DataMapper` uses `TargetResolver` table-name derivation from the DTO short class name: CamelCase -> snake_case plural (`UserDto` -> `user_dtos`).
- Explicit table names for writes/reads are supported by passing a plain string target; column/property overrides and opt-in explicit field lists go through `Mapping`.
- Batch insert/upsert/update behavior is dialect-aware. SQLite tests exercise chunking explicitly because the SQLite dialect bind-parameter limit is `999`.
- Nested transactions are opt-in via `new Connection($pdo, true)` or `Connection::create(..., nestTransactions: true)` and use savepoints.

## Testing Reality
- The test suite is fast and self-contained: tests use in-memory SQLite (`sqlite::memory:`) rather than external services.
- QueryBuilder and DataMapper changes should usually be covered by or updated alongside the SQLite-backed tests in `tests/QueryBuilder/` and `tests/V2DataMapperTest.php` / `tests/ConnectionEdgeCasesTest.php`.

## Artifacts
- `composer.lock` is gitignored in this library repo.
- Generated local artifacts to ignore/include carefully when reviewing changes: `vendor/`, `.phpunit.cache/`, `.php-cs-fixer.cache`, `coverage.xml`, `build/`.
