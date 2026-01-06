# API Documentation

Full API documentation for the codesaur/dataobject package.

---

## Table of Contents

- [Column](#column)
- [Model](#model)
- [LocalizedModel](#localizedmodel)
- [PDOTrait](#pdotrait)
- [TableTrait](#tabletrait)

---

## Column

**Namespace:** `codesaur\DataObject\Column`

**Description:** Class for defining the structure of a single SQL table column.

This includes all configurations for table creation:
- column name
- data type
- length/size
- NULL or NOT NULL
- default value
- PRIMARY KEY, UNIQUE
- AUTO INCREMENT

### Constructor

```php
public function __construct(string $name, string $type, int|string|null $length = null)
```

**Parameters:**
- `$name` (string) - Column name
- `$type` (string) - Data type
- `$length` (int|string|null) - Type length/size

### Methods

#### `default(string|int|float|bool|null $default): Column`

Set default value.

**Parameters:**
- `$default` (string|int|float|bool|null) - Default value

**Returns:** `$this` (fluent interface)

---

#### `auto(bool $auto = true): Column`

Set AUTO_INCREMENT.

**Parameters:**
- `$auto` (bool) - Whether AUTO_INCREMENT

**Returns:** `Column`

---

#### `unique(bool $unique = true): Column`

Make column UNIQUE.

**Parameters:**
- `$unique` (bool) - Whether UNIQUE

**Returns:** `Column`

---

#### `primary(bool $primary = true): Column`

Make PRIMARY KEY.

**Parameters:**
- `$primary` (bool) - Whether PRIMARY KEY

**Returns:** `Column`

---

#### `notNull(bool $not_null = true): Column`

Set NOT NULL.

**Parameters:**
- `$not_null` (bool) - Whether NOT NULL

**Returns:** `Column`

---

#### `getName(): string`

Get column name.

**Returns:** Column name

---

#### `getType(): string`

Get column data type.

**Returns:** Data type (varchar, int, bigint, etc.)

---

#### `getDataType(): int`

Determine data type for PDO.

**Returns:** `PDO::PARAM_*` type

---

#### `getLength(): int|string|null`

Get type length.

**Returns:** Length/size (255 for VARCHAR(255)), or null

---

#### `getDefault(): string|int|float|bool|null`

Get default value.

**Returns:** Default value, or null

---

#### `isAuto(): bool`

Whether AUTO_INCREMENT.

**Returns:** Whether AUTO_INCREMENT is set

---

#### `isString(): bool`

Whether text type.

**Returns:** Whether text type like varchar, text, blob

**Supported types:** varchar, text, blob, binary, varbinary, char, tinytext, mediumtext, longtext, tinyblob, mediumblob, longblob, enum, set

---

#### `isInt(): bool`

Whether integer type.

**Returns:** Whether numeric type like int, bigint, smallint, tinyint

**Supported types:** int, bigint, integer, smallint, int8, bigserial, serial, tinyint, mediumint, bool, boolean

---

#### `isDecimal(): bool`

Decimal numbers.

**Returns:** Whether decimal type like decimal, float, double

**Supported types:** decimal, numeric, float, double, real

---

#### `isDateTime(): bool`

Date/time types.

**Returns:** Whether time type like datetime, date, timestamp

**Supported types:** datetime, date, timestamp, time, timestamptz, year

---

#### `isBit(): bool`

Whether BIT type.

**Returns:** Whether BIT type

---

#### `isNumeric(): bool`

Whether numeric values.

**Returns:** Whether includes integer, decimal, bit types

---

#### `isNull(): bool`

Whether NULL is allowed.

**Returns:** Whether NULL value is allowed (true=allows, false=NOT NULL)

---

#### `isPrimary(): bool`

Whether PRIMARY KEY.

**Returns:** Whether PRIMARY KEY column

---

#### `isUnique(): bool`

Whether UNIQUE.

**Returns:** Whether UNIQUE constraint exists

---

## Model

**Namespace:** `codesaur\DataObject\Model`

**Description:** Base class for models targeting a single table in the DataObject ecosystem.

This class uses TableTrait to:
- manage table structure
- create/check tables and columns
- add data (insert)
- update data (updateById)
- get rows (getRow, getRows)
- get rows by WHERE condition (getRowWhere)

and fully implements basic CRUD operations.

All single-language (non-localized) table models extend this class.

**Abstract Class** - Must be extended.

**Uses:** `TableTrait`

### Methods

#### `insert(array $record): array|false`

Add data (INSERT).

MySQL/SQLite → uses `lastInsertId()`  
PostgreSQL → uses `RETURNING *`

**Parameters:**
- `$record` (array) - Key → value pairs for the row to add

**Returns:** On success, full information of the new row (array with all columns), false on error

**Exception:** `Exception`

---

#### `updateById(int $id, array $record): array|false`

Update row by ID (UPDATE).

`UPDATE table SET field=:value WHERE id=X`

PostgreSQL → `RETURNING *`  
MySQL/SQLite → `SELECT * WHERE id=...`

**Parameters:**
- `$id` (int) - ID to update
- `$record` (array) - Fields to update ['column' => value, ...]

**Returns:** Full information of updated row (array with all columns), false on error

**Exception:** `Exception` - If table doesn't have primary auto increment id column or update data is empty

---

#### `getRows(array $condition = []): array`

Get multiple rows.

**Parameters:**
- `$condition` (array) - Conditions for SELECTStatement (JOIN, WHERE, ORDER, LIMIT…)

**Returns:** Array indexed by Primary ID (if ID exists), or simple array (if no ID)

---

#### `getRow(array $condition = []): array|null`

Get a single row.

**Parameters:**
- `$condition` (array) - SELECT conditions

**Returns:** Row information (array), or null (if not found)

---

#### `getRowWhere(array $with_values): array|null`

Get row using WHERE key=:value syntax.

**Parameters:**
- `$with_values` (array) - key => value array

**Returns:** Row information (array), or null (if not found)

---

## LocalizedModel

**Namespace:** `codesaur\DataObject\LocalizedModel`

**Description:** Base class for 2-table model pattern for storing localized content in multiple languages.

**Architecture:**
- Primary table → id, is_active, sort, etc. universally shared columns
- Content table (table_content) → fields to store in multiple languages

**Content table structure:**
- `id` (primary)
- `parent_id` (FK → primary table.id)
- `code` (language code, e.g., 'en', 'mn', 'jp')
- other fields to store in that language

By using this class:
- fully automates localized insert/update
- merges localized rows and returns as unified array
- no code duplication needed

**Abstract Class** - Must be extended.

**Uses:** `TableTrait`

### Properties

#### `protected readonly array $contentColumns`

Content table columns.

### Methods

#### `setTable(string $name): void`

Set table name and create primary and content tables.

**Parameters:**
- `$name` (string) - Table name

**Exception:** `Exception` - If columns are not defined or id column doesn't exist

---

#### `getContentName(): string`

Automatically generated content table name.

**Returns:** `{table_name}_content`

---

#### `getContentColumns(): array`

Get content table columns.

**Returns:** `Column[]` array

---

#### `getContentColumn(string $name): Column`

Get specific content table column.

**Parameters:**
- `$name` (string) - Column name

**Returns:** `Column` object

**Exception:** `Exception` - If column not found

---

#### `setContentColumns(array $columns): void`

Set content table columns.

**Parameters:**
- `$columns` (Column[]) - Array of Column objects

**Exception:** `Exception` - If object is not Column or invalid column is set

---

#### `insert(array $record, array $content): array|false`

Add row with multi-language content.

**Parameters:**
- `$record` (array) - Primary table data: ['column' => value, ...]
  - Example: `['name' => 'product', 'status' => 'active']`
- `$content` (array) - Content grouped by language: ['mn' => [col => val], 'en' => [...], ...]
  - Example: `['en' => ['title' => 'English', 'description' => '...'], 'mn' => [...]]`

**Returns:** Returns new row; false on failure

**Exception:** `Exception`, `InvalidArgumentException` - If content is empty or error occurs

**Return value structure:** Same as `getRow()` return value structure

---

#### `updateById(int $id, array $record, array $content): array|false`

Update row with multi-language content by id column.

**Parameters:**
- `$id` (int) - ID to update
- `$record` (array) - Primary table update: ['column' => value, ...]
  - Example: `['read_count' => 10]`
- `$content` (array) - Content update grouped by language: ['mn' => [col => val], 'en' => [...], ...]
  - Example: `['en' => ['title' => 'New title'], 'mn' => ['description' => 'Шинэ тайлбар']]`

**Returns:** Returns updated row; false on failure

**Exception:** `Exception` - If update data is empty or error occurs

**Return value structure:** Same as `getRow()` return value structure

---

#### `select(string $selection = '*', array $condition = []): \PDOStatement`

Select from dual tables (primary + content) with JOIN.

**Parameters:**
- `$selection` (string) - Columns to select ('*' or 'col1, col2, ...')
- `$condition` (array) - SELECT conditions

**Returns:** `PDOStatement`

---

#### `getRows(array $condition = []): array`

Get multiple rows (with multiple languages).

**Parameters:**
- `$condition` (array) - SELECT conditions

**Returns:** Array `[primary_id => rowStructure]`, rowStructure has same structure as `getRow()` return value

**Example:**
```php
[
    1 => [
        'id' => 1,
        'name' => 'product_name',
        'status' => 'active',
        'localized' => [
            'en' => [
                'title' => 'English Title',
                'description' => 'English Description'
            ],
            'mn' => [
                'title' => 'Монгол Гарчиг',
                'description' => 'Монгол Тайлбар'
            ]
        ]
    ],
    ...
]
```

---

#### `getRow(array $condition): array|null`

Get a single row with multiple languages.

**Parameters:**
- `$condition` (array) - SELECT conditions (WHERE, JOIN, ORDER, LIMIT, etc.)

**Returns:** On success, array with following structure, null if not found:
- All primary table column values at direct level (e.g., 'id', 'name', 'status', etc.)
- Multi-language contents under 'localized' key:
  - First level: language code (e.g., 'en', 'mn', 'ru', etc.)
  - Second level: content column name (e.g., 'title', 'description', etc.)
  - Value: content value in that language

**Example:**
```php
[
    'id' => 1,
    'name' => 'product_name',
    'status' => 'active',
    'localized' => [
        'en' => [
            'title' => 'English Title',
            'description' => 'English Description'
        ],
        'mn' => [
            'title' => 'Монгол Гарчиг',
            'description' => 'Монгол Тайлбар'
        ]
    ]
]
```

---

#### `getRowWhere(array $with_values): array|null`

Get row (with multiple languages) by WHERE key=value condition.

**Parameters:**
- `$with_values` (array) - key => value array

**Returns:** Same structure as `getRow()` return value; null if not found

**Note:** This function calls `getRow()`

---

#### `getRowsByCode(string $code, array $condition = []): array`

Get multiple rows by specific language code.

This function returns only the specified language's content when given a language code.

**Parameters:**
- `$code` (string) - Language code (e.g., 'en', 'mn', 'ru')
- `$condition` (array) - SELECT conditions (WHERE, JOIN, ORDER, LIMIT, etc., code condition is automatically added)

**Returns:** Array `[primary_id => rowStructure]`, rowStructure has following structure:
- All primary table column values at direct level
- Only that language's content under 'localized' key (no language code level)

**Example:** When code='en':
```php
[
    1 => [
        'id' => 1,
        'name' => 'product_name',
        'status' => 'active',
        'localized' => [
            'title' => 'English Title',
            'description' => 'English Description'
        ]
    ],
    2 => [
        'id' => 2,
        'name' => 'another_product',
        'status' => 'draft',
        'localized' => [
            'title' => 'Another Title',
            'description' => 'Another Description'
        ]
    ]
]
```

**Note:** Function to get all languages' content - `getRows()`, Function to get a single row - `getRow()`

---

## PDOTrait

**Namespace:** `codesaur\DataObject\PDOTrait`

**Description:** This trait centralizes general database operations (prepare, query, exec, quote, get driver type) based on PDO into a single standard interface.

All Model and Table-related classes in the codesaur ecosystem use this trait to:

- share PDO instance
- reliably prepare SQL statements
- throw errors in standard format
- automatically detect MySQL/PostgreSQL/SQLite driver
- manage configurations like FOREIGN KEY CHECKS

**Trait**

### Properties

#### `protected \PDO $pdo`

PDO instance.

---

#### `private ?string $_driver`

Driver name in use (mysql | pgsql | sqlite).

### Methods

#### `setInstance(\PDO $pdo): void`

Set PDO instance to model.

**Parameters:**
- `$pdo` (\PDO) - PDO instance

---

#### `getDriverName(): string|null`

Return PDO driver name in use.

**Returns:** Driver name (mysql, pgsql, sqlite)

---

#### `quote(string $string, int $parameter_type = \PDO::PARAM_STR): string|false`

Escape SQL string in driver-appropriate format.

**Parameters:**
- `$string` (string) - Text to use in SQL
- `$parameter_type` (int) - PDO::PARAM_* type

**Returns:** Escaped string, or false

---

#### `prepare(string $statement, array $driver_options = []): \PDOStatement`

Prepare SQL statement and return PDOStatement.  
Throws standard Exception on failure.

**Parameters:**
- `$statement` (string) - SQL to prepare
- `$driver_options` (array) - PDO driver options

**Returns:** `PDOStatement`

**Exception:** `Exception` - If PDO error occurs

---

#### `exec(string $statement): int|false`

Execute SQL command directly (DDL/DML).

**Parameters:**
- `$statement` (string) - SQL command (CREATE, DROP, UPDATE, etc.)

**Returns:** Number of affected rows or false

---

#### `query(string $statement): \PDOStatement`

Execute query directly without preparation.

**Parameters:**
- `$statement` (string) - SQL SELECT

**Returns:** `PDOStatement`

**Exception:** `Exception` - If PDO error occurs

---

#### `hasTable(string $table): bool`

Check if table exists in database.

**Parameters:**
- `$table` (string) - Table name

**Returns:** Whether table exists

**Exception:** `RuntimeException` - If unsupported driver

**Supported drivers:**
- MySQL: `SHOW TABLES LIKE ...`
- PostgreSQL: `SELECT ... FROM pg_tables ...`
- SQLite: `SELECT ... FROM sqlite_master ...`

---

#### `setForeignKeyChecks(bool $enable): int|false`

Enable/disable FOREIGN KEY constraints.

- MySQL → `SET foreign_key_checks = 0|1`
- PostgreSQL → `SET session_replication_role = 'replica'|'origin'`
- SQLite → `PRAGMA foreign_keys = ON|OFF`

**Parameters:**
- `$enable` (bool) - TRUE=enable, FALSE=disable

**Returns:** Execution result (int), or false

**Exception:** `RuntimeException` - If unsupported driver

---

## TableTrait

**Namespace:** `codesaur\DataObject\TableTrait`

**Description:** This trait fully contains basic capabilities for working with tables in the database.

Including:
- Table name and column definitions
- Automatic table creation logic (adapted for MySQL/PostgreSQL/SQLite)
- PRIMARY, UNIQUE column validation
- CRUD helper operations (deleteById, deactivateById)
- SELECT statement builder (JOIN, WHERE, LIMIT…)
- Check if table exists

This trait is the base foundation for Model and LocalizedModel.

**Trait**

**Uses:** `PDOTrait`

### Properties

#### `protected readonly string $name`

SQL table name.

---

#### `protected readonly array $columns`

SQL table column definitions.  
Array of Column objects.

### Abstract Methods

#### `public abstract function __construct(\PDO $pdo)`

Model constructor - PDO must be passed.

**Parameters:**
- `$pdo` (\PDO) - PDO instance

---

#### `protected abstract function __initial(): void`

Initial configuration after table is first created on actual database with CREATE.

### Methods

#### `__destruct(): void`

Destructor - releases PDO.

---

#### `getName(): string`

Get table name.

**Returns:** Table name

**Exception:** `Exception` - If table name is not set

---

#### `setTable(string $name): void`

Set table name and create table.

Filters name with allowed characters.

**Parameters:**
- `$name` (string) - Table name

**Exception:** `Exception` - If columns are not defined or error occurs creating table

---

#### `getColumns(): array`

Return all table columns.

**Returns:** `Column[]` array

**Exception:** `Exception` - If columns are not defined

---

#### `setColumns(array $columns): void`

Set table columns validated as Column objects.

**Parameters:**
- `$columns` (Column[]) - Array of Column objects

**Exception:** `Exception` - If object is not Column

---

#### `getColumn(string $name): Column`

Return column by name.

**Parameters:**
- `$name` (string) - Column name

**Returns:** `Column` object

**Exception:** `Exception` - If column not found

---

#### `hasColumn(string $name): bool`

Whether column with name exists.

**Parameters:**
- `$name` (string) - Column name

**Returns:** Whether column exists

---

#### `deleteById(int $id): bool`

Delete row by ID.

**Parameters:**
- `$id` (int) - ID to delete

**Returns:** Whether successful

**Exception:** `Exception` - If id column is not primary auto increment

---

#### `deactivateById(int $id, array $record = []): bool`

Deactivate row by ID (soft delete).

To prevent UNIQUE column value conflicts, uses following method:
- Numeric unique → converts to -value
- Text unique → adds [uniqid] prefix

**Parameters:**
- `$id` (int) - ID to deactivate
- `$record` (array) - Additional update fields

**Returns:** Whether successful

**Exception:** `Exception` - If id column is not primary auto increment or is_active column doesn't exist

---

#### `protected final function createTable(string $table, array $columns): void`

Create SQL table (adapted for MySQL/PostgreSQL/SQLite).

**Parameters:**
- `$table` (string) - Table name
- `$columns` (Column[]) - Array of columns

**Exception:** `Exception` - If error occurs creating table

---

#### `selectStatement(string $fromTable, string $selection = '*', array $condition = []): \PDOStatement`

Flexible SELECT statement builder.

**Parameters:**
- `$fromTable` (string) - FROM table name
- `$selection` (string) - Columns to select ('*' or 'col1, col2, ...')
- `$condition` (array) - Conditions:
  - `'JOIN'` / `'INNER JOIN'` / `'LEFT JOIN'` / `'RIGHT JOIN'` / `'CROSS JOIN'` - JOIN clause
  - `'WHERE'` - WHERE clause (example: 'field=:param AND field2>:param2')
  - `'GROUP BY'` - GROUP BY clause
  - `'HAVING'` - HAVING clause
  - `'ORDER BY'` - ORDER BY clause
  - `'LIMIT'` - LIMIT clause
  - `'OFFSET'` - OFFSET clause
  - `'PARAM'` - Parameter array [':param' => value, ...]

**Returns:** Prepared statement (`PDOStatement`)

**Exception:** `Exception` - If error occurs executing SELECT

**Example:**
```php
$stmt = $model->selectStatement('users', '*', [
    'WHERE' => 'is_active = :active AND age > :age',
    'ORDER BY' => 'created_at DESC',
    'LIMIT' => 10,
    'PARAM' => [
        ':active' => 1,
        ':age' => 18
    ]
]);
```

---

#### `protected function getSyntax(Column $column): string`

Generate SQL syntax for Column object.  
Converts types adapted for MySQL/PGSQL/SQLite.

**Parameters:**
- `$column` (Column) - Column object

**Returns:** SQL format (example: `id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY`)

**Type conversions:**
- **PostgreSQL:** int8→bigint, integer→int, tinyint→smallint, datetime→timestamp, tinytext→text, bigint+auto→bigserial, int+auto→serial
- **SQLite:** All integer types→INTEGER, decimal/float→REAL, blob→BLOB, others→TEXT
- **MySQL:** bigserial→bigint, serial→int, smallserial→smallint, timestamptz→timestamp

---

## Database Support

### Supported Databases

- **MySQL** - Full support
- **PostgreSQL** - Full support  
- **SQLite** - Full support

### Driver-Specific Features

#### MySQL
- `AUTO_INCREMENT` for auto-increment columns
- `SHOW TABLES` for table existence check
- `SET foreign_key_checks` for FK control
- Collation support

#### PostgreSQL
- `RETURNING *` clause for INSERT/UPDATE
- `serial`, `bigserial` for auto-increment
- `pg_tables` for table existence check
- `SET session_replication_role` for FK control

#### SQLite
- `AUTOINCREMENT` for auto-increment primary keys
- `sqlite_master` for table existence check
- `PRAGMA foreign_keys` for FK control
- Simplified type system (INTEGER, REAL, TEXT, BLOB)
