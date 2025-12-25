# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**🇬🇧 English Version | 🇲🇳 [Монгол Хувилбар](CHANGELOG.md)**

---

## [9.0.0] - 2025-12-25

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

## [8.1.0] - Previous Version

### Features

- Full support for MySQL, PostgreSQL, and SQLite
- `Model` class for non-localized tables
- `LocalizedModel` class for multi-language content
- Automatic table creation
- Comprehensive CRUD operations
- Unit and Integration tests
- CI/CD pipeline with GitHub Actions

---

[9.0.0]: https://github.com/codesaur-php/DataObject/compare/v8.1.0...v9.0.0
[8.1.0]: https://github.com/codesaur-php/DataObject/releases/tag/v8.1.0
