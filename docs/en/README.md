# codesaur/dataobject

**PDO-based data model and table management component (MySQL / PostgreSQL / SQLite, PHP 8.2.1+)**

`codesaur/dataobject` is the core data layer component of the **codesaur-php** ecosystem.
Instead of using raw `PDO` directly, it:

- defines table structures **using Column classes within PHP classes**,
- **automatically creates tables** on first use,
- works **with the same code** on MySQL / PostgreSQL / SQLite.

Core idea:
> _"Hide all repetitive Schema + CRUD logic in reusable Model / LocalizedModel classes"_

---

## Requirements

- PHP **8.2.1+** with `ext-pdo` extension
- Composer
- MySQL or PostgreSQL or SQLite

---

## Installation

```bash
composer require codesaur/dataobject
```

## Running Tests

### Composer Test Commands

```bash
# Run all tests (Unit + Integration tests)
composer test

# Generate HTML coverage report (in coverage/ directory)
composer test-coverage
```

### Command Descriptions

- **`composer test`**
  - Runs all tests (Unit and Integration)
  - Tests using PHPUnit
  - Displays test results in terminal
  - Returns success/failure status

- **`composer test-coverage`**
  - Runs all tests + generates code coverage report
  - Generates HTML format coverage report (`coverage/` directory)
  - Can open `coverage/index.html` file in browser
  - Shows coverage percentage for each line, function, and class

### Test Information

- **Unit Tests**: Tests for Column, PDOTrait, TableTrait, Model classes
- **Integration Tests**: Full tests for LocalizedModel
- **Total**: 107 tests, 279 assertions

### Using PHPUnit Directly

Instead of Composer commands, you can run PHPUnit directly:

```bash
# Run all tests
vendor/bin/phpunit

# Only Unit tests
vendor/bin/phpunit tests/Unit

# Only Integration tests
vendor/bin/phpunit tests/Integration

# Coverage report (Clover XML format)
vendor/bin/phpunit --coverage-clover coverage/clover.xml

# Coverage report (HTML format)
vendor/bin/phpunit --coverage-html coverage
```

**Windows users:** Replace `vendor/bin/phpunit` with `vendor\bin\phpunit.bat`

---

## Core Classes

# **Constants**

Centralized class for all constant values used across the codebase:

- **Driver names:** `DRIVER_MYSQL`, `DRIVER_PGSQL`, `DRIVER_SQLITE`
- **Error codes:** `ERR_TABLE_NAME_MISSING`, `ERR_COLUMNS_NOT_DEFINED`, `ERR_COLUMN_NOT_FOUND`
- **Column names:** `COL_ID`, `COL_IS_ACTIVE`, `COL_PARENT_ID`, `COL_CODE`
- **Localized model:** `CONTENT_TABLE_SUFFIX`, `CONTENT_KEY_COLUMNS`, `LOCALIZED_KEY`, `PRIMARY_ALIAS_PREFIX`, `CONTENT_ALIAS_PREFIX`, `DEFAULT_CODE_LENGTH`
- **Configuration:** `TABLE_NAME_PATTERN`, `MYSQL_ENGINE`

```php
use codesaur\DataObject\Constants;

// Driver check
if ($driver == Constants::DRIVER_MYSQL) { ... }

// Column name reference
$this->hasColumn(Constants::COL_ID);

// Content table name
$contentTable = $tableName . Constants::CONTENT_TABLE_SUFFIX;
```

---

# **Column**

Metadata for a single column:

- name (`name`)
- type (`type` - int, varchar, datetime, ...)
- length (`length`)
- NULL / NOT NULL
- PRIMARY / UNIQUE / AUTO_INCREMENT
- default value (`default`)

```php
use codesaur\DataObject\Column;

$columns = [
   (new Column('id', 'bigint'))->primary(),
   (new Column('username', 'varchar', 65))->unique(),
    new Column('password', 'varchar', 255),
   (new Column('is_active', 'tinyint'))->default(1),
];
```

---

# **Model**

Base class for simple (non-localized) tables.

-Table name and columns via `setTable()` / `setColumns()`
-CRUD: `insert()`, `updateById()`, `getRow()`, `getRows()`, `getRowWhere()`
-`getById()`, `existsById()`, `countRows()`
-`deleteById()`, `deactivateById()`
-Automatically handles MySQL / PostgreSQL / SQLite differences

```php
use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class UserModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('username', 'varchar', 64))->unique(),
            new Column('password', 'varchar', 255),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
        ]);

        $this->setTable('users');
    }

    protected function __initial()
    {
        // Runs once when table is first created
    }

    // Example: add user
    public function createUser(string $username, string $hashedPassword): array
    {
        return $this->insert([
            'username'   => $username,
            'password'   => $hashedPassword,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Example: update user activation status
    public function setActive(int $id, bool $active): array
    {
        return $this->updateById($id, [
            'is_active' => $active ? 1 : 0,
        ]);
    }
}
```

---

# **LocalizedModel**

Base class for tables that need to store content in multiple languages.

## Architecture:

-PRIMARY table: `tablename`
-CONTENT table: `tablename_content`

Inside CONTENT table:

- `parent_id` -> FK -> primary.id (CASCADE update)
- `code` -> language code (mn, en, jp ...)
- other fields (`title`, `description`, ...)

## Core Functions:

-**CRUD:** `insert($record, $content)`, `updateById($id, $record, $content)`
-**Read:** `getById($id)`, `existsById($id)`, `countRows($condition)`, `getRow($condition)`, `getRows($condition)`, `getRowWhere($values)`, `getRowsByCode($code, $condition)`
-Automatically handles MySQL / PostgreSQL / SQLite differences

## Return Value Structure:

`getRow()`, `getRows()`, `insert()`, `updateById()` functions return an array with the following structure:

```php
[
    'id' => 1,
    'slug' => 'article-slug',
    'is_active' => 1,
    'localized' => [
        'en' => [
            'title' => 'English Title',
            'body' => 'English content...'
        ],
        'mn' => [
            'title' => 'Монгол гарчиг',
            'body' => 'Монгол агуулга...'
        ]
    ]
]
```

`getRowsByCode('en', $condition)` function returns multiple rows with only the specified language code (no language code level):

```php
[
    1 => [
        'id' => 1,
        'slug' => 'article-slug-1',
        'is_active' => 1,
        'localized' => [
            'title' => 'English Title 1',
            'body' => 'English content 1...'
        ]
    ],
    2 => [
        'id' => 2,
        'slug' => 'article-slug-2',
        'is_active' => 1,
        'localized' => [
            'title' => 'English Title 2',
            'body' => 'English content 2...'
        ]
    ]
]
```

```php
use codesaur\DataObject\LocalizedModel;
use codesaur\DataObject\Column;

class ArticleModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('slug', 'varchar', 128))->unique(),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
        ]);

        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('body', 'text'),
        ]);

        $this->setTable('article');
    }

    // Example: add article (primary + localized)
    public function createArticle(string $slug, array $content): array
    {
        return $this->insert(
            [
                'slug'       => $slug,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            $content // ['en' => ['title' => '...', 'body' => '...'], 'mn' => [...]]
        );
    }

    // Example: update article content
    public function updateArticle(int $id, array $content, array $record = []): array
    {
        return $this->updateById($id, $record, $content);
    }

    // Example: get article by language
    public function getArticleByLang(int $id, string $lang): array|null
    {
        $rows = $this->getRowsByCode($lang, ['WHERE' => "p.id=$id"]);
        return $rows[$id] ?? null;
    }

    // Example: get all articles by language
    public function getAllArticlesByLang(string $lang, bool $activeOnly = true): array
    {
        $condition = ['ORDER BY' => 'p.created_at DESC'];
        if ($activeOnly) {
            $condition['WHERE'] = 'p.is_active=1';
        }
        return $this->getRowsByCode($lang, $condition);
    }

    // Example: get article by slug and language (using WHERE + PARAM)
    public function getArticleBySlugAndLang(string $slug, string $lang): array|null
    {
        $rows = $this->getRowsByCode($lang, [
            'WHERE' => 'p.slug=:slug AND p.is_active=1',
            'PARAM' => [':slug' => $slug]
        ]);
        return !empty($rows) ? \reset($rows) : null;
    }
}
```

---

### `PDOTrait`

`PDOTrait` centralizes all low-level logic for working with actual `PDO` instances.

**Core Capabilities:**

- `setInstance(PDO $pdo)` - installs PDO from outside
- `getDriverName()` - returns driver name (cached) like `mysql`, `pgsql`, `sqlite`
- `quote()`, `prepare()`, `exec()`, `query()` - **safe wrapper** for PDO's core functions
  - `prepare()` / `query()` throws **Exception** when returning `false`
- `hasTable($name)` - checks if table exists using different SQL for MySQL / PostgreSQL / SQLite
- `setForeignKeyChecks(bool $enable)` - temporarily disable / enable FK constraints
  - **MySQL:** `SET foreign_key_checks = 0|1`
  - **PostgreSQL:** `SET session_replication_role = 'replica'|'origin'`

This way, `Model` / `LocalizedModel` on top knows **not PDO code**, only their **business logic**.

---

### `TableTrait`

`TableTrait` handles **schema-level** operations using `PDOTrait`:

- table name (`$name`)
- column definitions (`$columns`)
- table creation / checking / populating with initial data

**Core Functions:**

- `setColumns(array $columns)` - indexes and stores `Column` array by name
- `setTable(string $name)`
  - cleans table name (uses `A-z 0-9 _-`)
  - checks if columns are properly defined
  - if table doesn't exist -> calls `createTable()` to **automatically create**
  - then runs model's `__initial()` **once**
- `getColumns()` / `getColumn($name)` / `hasColumn($name)` - schema introspection
- `deleteById($id)` - deletes row using primary key
- `deactivateById($id, array $record = [])`
  - sets `is_active` column to `0`
  - UNIQUE columns remain unchanged
- `selectStatement($fromTable, $selection='*', array $condition=[])`
  - All of JOIN / WHERE / GROUP BY / ORDER / LIMIT / OFFSET
    ```php
    ['INNER JOIN' => '...', 'WHERE' => '...', 'PARAM' => [...]]
    ```
    format to enable **dynamic SELECT** generation
- `createTable($table, array $columns)` / `getSyntax(Column $column)`
  - MySQL / PostgreSQL / SQLite type mapping
    - `serial`, `bigserial`, `timestamptz`, `tinyint` vs `smallint`, ...
  - PRIMARY, UNIQUE, AUTO_INCREMENT, DEFAULT, NULL/NOT NULL
    all automatically assembled into **clean SQL**

Finally, `Model` / `LocalizedModel` **"just declare columns, and when setTable() is called"** the table creates itself.

---

## Example Runner UI

Example code models are fully included in the Example directory.

- `example/index.php` - UI to choose MySQL/PostgreSQL/SQLite

## Tests and CI/CD

The project is fully tested:

-**PHPUnit** - Unit and Integration tests
-**GitHub Actions** - Automated CI/CD pipeline
  - Automatically runs on Push and Pull Request
  - Triggers on `main`, `master`, `develop` branches
-**Code Coverage** - HTML coverage report: `coverage/` directory
-**Multi-version** - Tested on PHP 8.2, 8.3
-**Multi-OS** - Tested on Ubuntu, Windows
-**Database Extensions** - Installs PDO, PDO_SQLite, PDO_MySQL, PDO_PgSQL

CI/CD workflow file: `.github/workflows/ci.yml`

---

## Additional Documentation

- **[API](api.md)** - Full API documentation (automatically generated from PHPDoc using Cursor AI)
- **[REVIEW](review.md)** - Code review results, found issues, improvement suggestions (generated using Cursor AI)
- **[CHANGELOG](../../CHANGELOG.md)** - History of all package version changes

---

# License

This project is licensed under MIT.

# Author

**Narankhuu**  
https://github.com/codesaur

---

# Conclusion

`codesaur/dataobject` is:

- Defines tables and columns **using PHP code**
- Works **with the same code** on MySQL / PostgreSQL / SQLite
- **Automatically handles** CRUD and schema initialization
- Makes data layer **clean and elegant**
- Lightweight, flexible, easy to extend component

If you want to use **standardized, reusable, clean data models** in your PHP project, this is the right choice!
