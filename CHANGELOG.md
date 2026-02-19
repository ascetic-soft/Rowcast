# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-02-20

### Added

- `ConnectionInterface` — abstract contract for database connections, enabling custom implementations and easier testing.
- `Connection::getDriverName()` — returns the PDO driver name without accessing PDO directly.
- `Persistence\ValueConverterInterface` — extensible contract for converting PHP values to DB format (the write-side counterpart of `TypeCasterInterface`).
- `Persistence\ValueConverterRegistry` — chain-of-responsibility registry for value converters.
- `Persistence\BoolValueConverter` — converts `bool` to `int` (0/1).
- `Persistence\EnumValueConverter` — converts `BackedEnum` to its backing value.
- `Persistence\DateTimeValueConverter` — converts `DateTimeInterface` to a formatted string (customizable format).
- `Persistence\DtoExtractor` — extracts column/value pairs from DTO objects via Reflection.
- `QueryBuilder\Compiler\SqlCompilerInterface` — contract for SQL generation strategies.
- `QueryBuilder\Compiler\SelectCompiler` — generates SELECT SQL.
- `QueryBuilder\Compiler\InsertCompiler` — generates INSERT SQL.
- `QueryBuilder\Compiler\UpdateCompiler` — generates UPDATE SQL.
- `QueryBuilder\Compiler\DeleteCompiler` — generates DELETE SQL.

### Changed

- `DataMapper` now depends on `ConnectionInterface` instead of the concrete `Connection` class.
- `DataMapper` now delegates DTO extraction to `DtoExtractor` and value conversion to `ValueConverterInterface`.
- `DataMapper` constructor accepts optional `DtoExtractor` and `ValueConverterInterface` parameters.
- `QueryBuilder` now depends on `ConnectionInterface` instead of the concrete `Connection` class.
- `QueryBuilder::getSQL()` delegates to dedicated compiler classes instead of private methods.

### Removed

- `DataMapper::extractData()` — replaced by `Persistence\DtoExtractor::extract()`.
- `DataMapper::convertValueForDb()` — replaced by `Persistence\ValueConverterRegistry`.
- `QueryBuilder::getSelectSQL()`, `getInsertSQL()`, `getUpdateSQL()`, `getDeleteSQL()` — replaced by compiler classes.

[1.0.1]: https://github.com/ascetic-soft/Rowcast/compare/v1.0.0...v1.0.1
