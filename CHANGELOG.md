# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [9.0.1] - 2026-03-04
[9.0.1]: https://github.com/codesaur-php/DataObject/compare/v9.0.0...v9.0.1

### Changed

- Removed all emoji characters from documentation and project files
- Fixed Mongolian text capitalization errors ("Монгол Гарчиг" -> "Монгол гарчиг", "Монгол Тайлбар" -> "Монгол тайлбар")
- Replaced Unicode arrow `->` with ASCII `->` across all files (docs, source, examples, tests)

---

## [9.0.0] - 2026-01-07
[9.0.0]: https://github.com/codesaur-php/DataObject/compare/v8.1.0...v9.0.0

### Breaking Changes

- **Removed `getRowByCode()` method** from `LocalizedModel`
  - This method has been removed as it was redundant with `getRowsByCode()`
  - **Migration:** Use `getRowsByCode($code, $condition)` instead
  - Example: `getRowByCode($id, 'en')` -> `getRowsByCode('en', ['WHERE' => 'p.id=:id', 'PARAM' => [':id' => $id]])`

### Added

- **Project Documentation**
  - Created `CONTRIBUTING.md` with contribution guidelines
  - Created `SECURITY.md` for security policy

- **Bilingual Documentation (English & Mongolian)**
  - Added complete documentation in both English and Mongolian languages
  - **English Documentation** (`docs/en/`):
    - `README.md` - Full English version of README
    - `api.md` - Complete English API documentation
    - `review.md` - English code review documentation
  - **Mongolian Documentation** (`docs/mn/`):
    - `README.md` - Монгол хэл дээрх бүрэн танилцуулга
    - `api.md` - Монгол хэл дээрх API баримт бичиг
    - `review.md` - Монгол хэл дээрх код шалгалтын баримт бичиг
  - All documentation files include language switcher links for easy navigation

### Documentation

- Refactored main `README.md` with bilingual support
- Fixed file paths in documentation references
- Updated all code examples to use `getRowsByCode()` instead of removed `getRowByCode()`
- Enhanced PHPdoc comments throughout the codebase
- Improved documentation consistency across all files in both languages

---

## [8.1.0] - 2025-12-19
[8.1.0]: https://github.com/codesaur-php/DataObject/compare/v7.0.0...v8.1.0

### Added

- Full support for MySQL, PostgreSQL, and SQLite databases
- `Model` class for non-localized tables with comprehensive CRUD operations
- `LocalizedModel` class for multi-language content management
- Automatic table creation with column definitions
- Unit and Integration tests with PHPUnit
- CI/CD pipeline with GitHub Actions

### Changed

- Enhanced database driver detection and compatibility
- Improved error handling across all database operations
- Better support for different SQL dialects

---

## [7.0.0] - 2025-09-21
[7.0.0]: https://github.com/codesaur-php/DataObject/compare/v5.0.0...v7.0.0

### Breaking Changes

- **Removed `MultiModel` class** - Replaced with `LocalizedModel` for better naming and functionality
- **Migration:** Update all `MultiModel` references to `LocalizedModel`

### Added

- **New `LocalizedModel` class** - Improved replacement for `MultiModel`
  - Better PostgreSQL support with `RETURNING` clause
  - Enhanced error handling for localized content operations
  - Improved insert/update methods with better transaction handling

### Changed

- Refactored localized content handling architecture
- Improved content table foreign key relationships
- Enhanced `insert()` method to return full row data instead of just ID
- Better error messages with detailed exception information

### Fixed

- Fixed PostgreSQL compatibility issues with `lastInsertId()`
- Improved transaction rollback on content insertion failures

---

## [5.0.0] - 2024-06-24
[5.0.0]: https://github.com/codesaur-php/DataObject/compare/v3.0...v5.0.0

### Breaking Changes

- **PHP version requirement upgraded** from PHP 7.2+ to PHP 8.2.1+
- **Removed `StatementTrait`** - Functionality merged into other traits

### Added

- Enhanced type safety with PHP 8.2+ features
- Improved error handling with better exception messages

### Changed

- Updated `composer.json` to require PHP ^8.2.1
- Refactored trait structure for better code organization
- Improved PDO error handling in `PDOTrait`

### Removed

- `StatementTrait` - Functionality integrated into `TableTrait` and `PDOTrait`

---

## [3.0] - 2021-10-20
[3.0]: https://github.com/codesaur-php/DataObject/compare/v2.0...v3.0

### Added

- **New `PDOTrait`** - Extracted PDO operations into reusable trait
  - Centralized PDO connection management
  - Improved error handling with detailed exception messages
  - Added `hasTable()` method for table existence checking
  - Added `setForeignKeyChecks()` method for foreign key management

- **New `StatementTrait`** - Statement handling functionality
  - `createTable()` method for table creation
  - `createTableVersion()` method for version table creation
  - `selectFrom()` method with comprehensive JOIN support (INNER, LEFT, RIGHT, CROSS)

### Changed

- Improved separation of concerns with trait-based architecture
- Enhanced error messages with PDO error information
- Better exception handling throughout the codebase

---

## [2.0] - 2021-04-06
[2.0]: https://github.com/codesaur-php/DataObject/compare/v1.0...v2.0

### Breaking Changes

- **Refactored `Table` class to `TableTrait`** - Converted from class to trait
- **`Model` class architecture changed** - Now uses `TableTrait` instead of extending `Table`
- Property names changed from public to private with underscore prefix (`$name` -> `$_name`, `$columns` -> `$_columns`)

### Added

- Trait-based architecture for better code reusability
- `__initial()` method hook for post-table creation initialization
- Improved table creation with collate support
- Enhanced delete operations with better condition handling
- `deactivate()` method for soft deletes using `is_active` column

### Changed

- Refactored from inheritance-based to composition-based design
- Improved `delete()` method with better condition array support
- Enhanced `create()` method with collate parameter
- Better error messages with class context

### Removed

- `Table` class - Replaced with `TableTrait`

---

## [1.0] - 2021-03-02
[1.0]: https://github.com/codesaur-php/DataObject/releases/tag/v1.0

### Initial Release

This version is the initial stable release of the `codesaur/dataobject` package.

### Added

- **Core Classes:**
  - `Table` class - Base table management with full CRUD operations
  - `Model` class - Extends `Table` for single-table models
  - `MultiModel` abstract class - Multi-language content management
  - `Column` class - Column definition and type management

- **Features:**
  - Table creation with column definitions
  - Automatic ID column generation
  - Foreign key support
  - Version table creation
  - Insert, Update, Delete operations
  - Select operations with WHERE, ORDER BY, LIMIT
  - Soft delete support via `is_active` column
  - Automatic timestamp management (`created_at`, `updated_at`)
  - User tracking (`created_by`, `updated_by`) via environment variables

- **Requirements:**
  - PHP 7.2 or newer
  - PDO extension
