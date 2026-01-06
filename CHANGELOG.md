# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [9.0.1] - 2026-01-06
[9.0.1]: https://github.com/codesaur-php/DataObject/compare/v9.0.0...v9.0.1

### ✨ Added

- Created `CONTRIBUTING.md` with contribution guidelines
- Created `SECURITY.md`

### 🔧 Changed

- Refactored `README.md`
- Improved documentation consistency across all files
- Fixed file paths in documentation references

---

## [9.0.0] - 2025-12-25
[9.0.0]: https://github.com/codesaur-php/DataObject/compare/v8.1.0...v9.0.0

### 🚨 Breaking Changes

- **Removed `getRowByCode()` method** from `LocalizedModel`
  - This method has been removed as it was redundant with `getRowsByCode()`
  - **Migration:** Use `getRowsByCode($code, $condition)` instead
  - Example: `getRowByCode($id, 'en')` → `getRowsByCode('en', ['WHERE' => 'p.id=:id', 'PARAM' => [':id' => $id]])`

### ✨ Added

- **English Documentation**
  - Added `README.EN.md` - Full English version of README
  - Added `API.EN.md` - Full English API documentation
  - Added `REVIEW.EN.md` - Full English code review documentation
  - All documentation files now include language switcher links

### 🔧 Changed

- **Documentation Structure**
  - All `.md` files now include language switcher links at the top
  - Improved documentation organization and accessibility

### 📝 Documentation

- Updated all examples to use `getRowsByCode()` instead of removed `getRowByCode()`
- Enhanced PHPdoc comments throughout the codebase
- Improved code examples in README files

---

## [8.1.0] - 2025-12-19
[8.1.0]: https://github.com/codesaur-php/DataObject/compare/v1.0...v8.1.0

### Features

- Full support for MySQL, PostgreSQL, and SQLite
- `Model` class for non-localized tables
- `LocalizedModel` class for multi-language content
- Automatic table creation
- Comprehensive CRUD operations
- Unit and Integration tests
- CI/CD pipeline with GitHub Actions

---

## [1.0] - 2021-03-2
[1.0]: https://github.com/codesaur-php/DataObject/releases/tag/v1.0

### 🎉 Initial Release

This version is the initial stable release of the `codesaur/dataobject` package.
