# API Documentation

codesaur/dataobject багцын бүрэн API баримт бичиг.

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

**Тайлбар:** SQL хүснэгтийн нэг баганын бүтцийг тодорхойлох зориулалттай класс.

Энэ нь хүснэгт үүсгэх үеийн:
- баганын нэр
- өгөгдлийн төрөл
- урт/хэмжээ
- NULL эсэх
- анхдагч утга
- PRIMARY KEY, UNIQUE эсэх
- AUTO INCREMENT эсэх
зэрэг бүх тохиргоог агуулна.

### Constructor

```php
public function __construct(string $name, string $type, int|string|null $length = null)
```

**Параметрүүд:**
- `$name` (string) - Баганын нэр
- `$type` (string) - Өгөгдлийн төрөл
- `$length` (int|string|null) - Төрлийн урт/хэмжээ

### Methods

#### `default(string|int|float|bool|null $default): Column`

Анхдагч утга тохируулах.

**Параметрүүд:**
- `$default` (string|int|float|bool|null) - Анхдагч утга

**Буцаах утга:** `$this` (fluent interface)

---

#### `auto(bool $auto = true): Column`

AUTO_INCREMENT тохируулах.

**Параметрүүд:**
- `$auto` (bool) - AUTO_INCREMENT эсэх

**Буцаах утга:** `Column`

---

#### `unique(bool $unique = true): Column`

UNIQUE багана болгох.

**Параметрүүд:**
- `$unique` (bool) - UNIQUE эсэх

**Буцаах утга:** `Column`

---

#### `primary(bool $primary = true): Column`

PRIMARY KEY болгох.

**Параметрүүд:**
- `$primary` (bool) - PRIMARY KEY эсэх

**Буцаах утга:** `Column`

---

#### `notNull(bool $not_null = true): Column`

NOT NULL тохируулах.

**Параметрүүд:**
- `$not_null` (bool) - NOT NULL эсэх

**Буцаах утга:** `Column`

---

#### `getName(): string`

Баганын нэр авах.

**Буцаах утга:** Баганын нэр

---

#### `getType(): string`

Баганын өгөгдлийн төрөл авах.

**Буцаах утга:** Өгөгдлийн төрөл (varchar, int, bigint гэх мэт)

---

#### `getDataType(): int`

PDO-д ашиглагдах өгөгдлийн төрөл тодорхойлох.

**Буцаах утга:** `PDO::PARAM_*` төрөл

---

#### `getLength(): int|string|null`

Төрлийн урт авах.

**Буцаах утга:** Урт/хэмжээ (VARCHAR(255) гэхэд 255), эсвэл null

---

#### `getDefault(): string|int|float|bool|null`

Анхдагч утга авах.

**Буцаах утга:** Анхдагч утга, эсвэл null

---

#### `isAuto(): bool`

AUTO_INCREMENT эсэх.

**Буцаах утга:** AUTO_INCREMENT тэмдэглэгдсэн эсэх

---

#### `isString(): bool`

Текстэн төрөл эсэх.

**Буцаах утга:** varchar, text, blob гэх мэт текстэн төрөл эсэх

**Дэмжсэн төрлүүд:** varchar, text, blob, binary, varbinary, char, tinytext, mediumtext, longtext, tinyblob, mediumblob, longblob, enum, set

---

#### `isInt(): bool`

Integer төрөл эсэх.

**Буцаах утга:** int, bigint, smallint, tinyint гэх мэт тоон төрөл эсэх

**Дэмжсэн төрлүүд:** int, bigint, integer, smallint, int8, bigserial, serial, tinyint, mediumint, bool, boolean

---

#### `isDecimal(): bool`

Аравтын тоонууд.

**Буцаах утга:** decimal, float, double гэх мэт аравтын төрөл эсэх

**Дэмжсэн төрлүүд:** decimal, numeric, float, double, real

---

#### `isDateTime(): bool`

Огноо/цаг төрлүүд.

**Буцаах утга:** datetime, date, timestamp гэх мэт цагийн төрөл эсэх

**Дэмжсэн төрлүүд:** datetime, date, timestamp, time, timestamptz, year

---

#### `isBit(): bool`

BIT төрөл эсэх.

**Буцаах утга:** BIT төрөл эсэх

---

#### `isNumeric(): bool`

Тоон утгууд эсэх.

**Буцаах утга:** Integer, decimal, bit төрлүүд багтах эсэх

---

#### `isNull(): bool`

NULL зөвшөөрөх эсэх.

**Буцаах утга:** NULL утга зөвшөөрөгдөх эсэх (true=зөвшөөрнө, false=NOT NULL)

---

#### `isPrimary(): bool`

PRIMARY KEY эсэх.

**Буцаах утга:** PRIMARY KEY багана эсэх

---

#### `isUnique(): bool`

UNIQUE эсэх.

**Буцаах утга:** UNIQUE constraint байгаа эсэх

---

## Model

**Namespace:** `codesaur\DataObject\Model`

**Тайлбар:** DataObject экосистемийн үндсэн нэг хүснэгтэд зориулсан загварын (model) суурь класс.

Энэ класс нь TableTrait-ийг ашиглан:
- хүснэгтийн бүтцийг удирдах
- хүснэгт үүсгэх / багана шалгах
- өгөгдөл нэмэх (insert)
- өгөгдөл шинэчлэх (updateById)
- мөр авах (getRow, getRows)
- WHERE нөхцлөөр мөр авах (getRowWhere)

зэрэг үндсэн CRUD үйлдлүүдийг бүрэн хэрэгжүүлдэг.

Бүх нэг хэлт (non-localized) хүснэгтийн моделиуд энэ классыг удамшуулна.

**Abstract Class** - Удамшуулах ёстой.

**Uses:** `TableTrait`

### Methods

#### `insert(array $record): array`

Өгөгдөл нэмэх (INSERT).

MySQL/SQLite -> `lastInsertId()` ашиглана
PostgreSQL -> `RETURNING *` ашиглана

**Параметрүүд:**
- `$record` (array) - Нэмэх мөрийн түлхүүр -> утга хослол

**Буцаах утга:** Амжилттай бол шинэ мөрийн бүрэн мэдээлэл (бүх багана агуулсан массив). Алдаа гарвал Exception шиднэ

**Exception:** `Exception`

---

#### `updateById(int $id, array $record): array`

ID-р мөр шинэчлэх (UPDATE).

`UPDATE table SET field=:value WHERE id=X`

PostgreSQL -> `RETURNING *`
MySQL/SQLite -> `SELECT * WHERE id=...`

**Параметрүүд:**
- `$id` (int) - Шинэчлэх ID
- `$record` (array) - Шинэчлэх талбарууд ['column' => value, ...]

**Буцаах утга:** Шинэчилсэн мөрийн бүрэн мэдээлэл (бүх багана агуулсан массив). Алдаа гарвал Exception шиднэ

**Exception:** `Exception` - Хүснэгтэд primary auto increment id багана байхгүй бол эсвэл шинэчлэх өгөгдөл хоосон бол

---

#### `existsById(int $id): bool`

ID-р мөр байгаа эсэхийг шалгах. `SELECT 1 ... LIMIT 1` ашиглана.

**Параметрүүд:**
- `$id` (int) - Шалгах ID

**Буцаах утга:** Мөр байгаа эсэх

---

#### `getById(int $id): array|null`

ID-р мөр авах. `getRowWhere(['id' => $id])` гэсэн товчлол.

**Параметрүүд:**
- `$id` (int) - Авах ID

**Буцаах утга:** Мөрийн мэдээлэл (массив), эсвэл null (олдохгүй бол)

---

#### `countRows(array $condition = []): int`

Нөхцөлд тохирох мөрийн тоог тоолох. `COUNT(*)` query ашиглана.

**Параметрүүд:**
- `$condition` (array) - WHERE, JOIN нөхцөл (LIMIT, OFFSET, ORDER BY шаардлагагүй)

**Буцаах утга:** Тохирох мөрийн тоо

---

#### `getRows(array $condition = []): array`

Олон мөрийг авах.

**Параметрүүд:**
- `$condition` (array) - SELECTStatement-д өгөх нөхцөл (JOIN, WHERE, ORDER, LIMIT...)

**Буцаах утга:** Primary ID-р индексжсэн массив (ID байвал), эсвэл энгийн массив (ID байхгүй бол)

---

#### `getRow(array $condition = []): array|null`

Нэг мөрийг авах.

**Параметрүүд:**
- `$condition` (array) - SELECT нөхцөл

**Буцаах утга:** Мөрийн мэдээлэл (массив), эсвэл null (олдохгүй бол)

---

#### `getRowWhere(array $with_values): array|null`

WHERE key=:value хэлбэрийн синтаксаар мөр авах.

**Параметрүүд:**
- `$with_values` (array) - key => value массив

**Буцаах утга:** Мөрийн мэдээлэл (массив), эсвэл null (олдохгүй бол)

---

## LocalizedModel

**Namespace:** `codesaur\DataObject\LocalizedModel`

**Тайлбар:** Олон хэл дээрх (localized) контент хадгалах зориулалттай 2 хүснэгтийн загварын суурь класс.

**Архитектур:**
- Үндсэн хүснэгт (primary table) -> id, is_active, sort гэх мэт universally shared баганууд
- Контент хүснэгт (table_content) -> олон хэл дээр хадгалах талбарууд

**Контент хүснэгтийн бүтэц:**
- `id` (primary)
- `parent_id` (FK -> primary table.id)
- `code` (хэлний код, ж: 'en', 'mn', 'jp')
- бусад тухайн хэл дээр хадгалах талбарууд

Энэ классыг ашигласнаар:
- localized insert/update-г бүрэн автоматжуулна
- localized мөрүүдийг нэгтгэн unified array хэлбэрээр буцаана
- кодын дахин бичлэг огт шаардлагагүй

**Abstract Class** - Удамшуулах ёстой.

**Uses:** `TableTrait`

### Properties

#### `protected readonly array $contentColumns`

Контент хүснэгтийн баганууд.

### Methods

#### `setTable(string $name): void`

Хүснэгтийн нэрийг тогтоож, үндсэн болон контент хүснэгтүүдийг үүсгэнэ.

**Параметрүүд:**
- `$name` (string) - Хүснэгтийн нэр

**Exception:** `Exception` - Баганууд тодорхойлогдоогүй эсвэл id багана байхгүй бол

---

#### `getContentName(): string`

Контент хүснэгтийн автоматаар угсрагдах нэр.

**Буцаах утга:** `{table_name}_content`

---

#### `getContentColumns(): array`

Контент хүснэгтийн багануудыг авах.

**Буцаах утга:** `Column[]` массив

---

#### `getContentColumn(string $name): Column`

Контент хүснэгтийн тодорхой багана авах.

**Параметрүүд:**
- `$name` (string) - Баганын нэр

**Буцаах утга:** `Column` объект

**Exception:** `Exception` - Багана олдохгүй бол

---

#### `setContentColumns(array $columns): void`

Контент хүснэгтийн багануудыг тогтоох.

**Параметрүүд:**
- `$columns` (Column[]) - Column объектуудын массив

**Exception:** `Exception` - Column биш объект эсвэл алдаатай багана тохируулсан бол

---

#### `insert(array $record, array $content): array`

Олон хэл дээрх контенттэй мөр нэмэх.

**Параметрүүд:**
- `$record` (array) - Primary хүснэгтийн өгөгдөл: ['column' => value, ...]
  - Жишээ: `['name' => 'product', 'status' => 'active']`
- `$content` (array) - Хэлээр бүлэглэсэн контент: ['mn' => [col => val], 'en' => [...], ...]
  - Жишээ: `['en' => ['title' => 'English', 'description' => '...'], 'mn' => [...]]`

**Буцаах утга:** Шинэ мөрийг буцаана. Алдаа гарвал Exception шиднэ

**Exception:** `Exception`, `InvalidArgumentException` - Контент хоосон эсвэл алдаа гарвал

**Буцаах утгын бүтэц:** `getRow()`-ийн буцаах утгын бүтэцтэй ижил

---

#### `updateById(int $id, array $record, array $content): array`

Олон хэл дээрх контенттэй мөрийг id багана барьж шинэчлэх.

**Параметрүүд:**
- `$id` (int) - Шинэчлэх ID
- `$record` (array) - Primary хүснэгтийн шинэчлэл: ['column' => value, ...]
  - Жишээ: `['read_count' => 10]`
- `$content` (array) - Хэлээр бүлэглэсэн контент шинэчлэл: ['mn' => [col => val], 'en' => [...], ...]
  - Жишээ: `['en' => ['title' => 'New title'], 'mn' => ['description' => 'Шинэ тайлбар']]`

**Буцаах утга:** Шинэчлэгдсэн мөрийг буцаана. Алдаа гарвал Exception шиднэ

**Exception:** `Exception` - Шинэчлэх өгөгдөл хоосон эсвэл алдаа гарвал

**Буцаах утгын бүтэц:** `getRow()`-ийн буцаах утгын бүтэцтэй ижил

---

#### `existsById(int $id): bool`

ID-р мөр байгаа эсэхийг шалгах. Primary хүснэгт дээр `SELECT 1 ... LIMIT 1` ашиглана.

**Параметрүүд:**
- `$id` (int) - Шалгах ID

**Буцаах утга:** Мөр байгаа эсэх

---

#### `getById(int $id): array|null`

ID-р мөр (олон хэлтэй) авах. `getRowWhere(['p.id' => $id])` гэсэн товчлол.

**Параметрүүд:**
- `$id` (int) - Авах ID

**Буцаах утга:** Олон хэлтэй мөрийн мэдээлэл (массив), эсвэл null (олдохгүй бол)

---

#### `countRows(array $condition = []): int`

Primary хүснэгт дээр мөрийн тоог тоолох. `COUNT(*)` query ашиглана. Content JOIN шаардлагагүй, `p.` prefix шаардлагагүй.

**Параметрүүд:**
- `$condition` (array) - WHERE нөхцөл

**Буцаах утга:** Тохирох мөрийн тоо

---

#### `select(string $selection = '*', array $condition = []): \PDOStatement`

Хос хүснэгтээс (primary + content) JOIN хийж сонгох.

**Параметрүүд:**
- `$selection` (string) - Сонгох баганууд ('*' эсвэл 'col1, col2, ...')
- `$condition` (array) - SELECT нөхцөлүүд

**Буцаах утга:** `PDOStatement`

---

#### `getRows(array $condition = []): array`

Олон мөрийг (олон хэлтэй) авах.

**Параметрүүд:**
- `$condition` (array) - SELECT нөхцөл

**Буцаах утга:** Массив `[primary_id => rowStructure]`, rowStructure нь `getRow()`-ийн буцаах бүтэцтэй ижил

**Жишээ:**
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
                'title' => 'Монгол гарчиг',
                'description' => 'Монгол тайлбар'
            ]
        ]
    ],
    ...
]
```

---

#### `getRow(array $condition): array|null`

Нэг мөрийг олон хэлтэйгээр авах.

**Параметрүүд:**
- `$condition` (array) - SELECT нөхцөл (WHERE, JOIN, ORDER, LIMIT гэх мэт)

**Буцаах утга:** Амжилттай бол дараах бүтэцтэй массив, олдохгүй бол null:
- Primary хүснэгтийн бүх багана утгууд шууд түвшинд (жишээ: 'id', 'name', 'status' гэх мэт)
- 'localized' түлхүүр дор олон хэлтэй контентууд:
  - Эхний түвшин: хэлний код (жишээ: 'en', 'mn', 'ru' гэх мэт)
  - Хоёрдугаар түвшин: контент баганын нэр (жишээ: 'title', 'description' гэх мэт)
  - Утга: тухайн хэл дээрх контентын утга

**Жишээ:**
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
            'title' => 'Монгол гарчиг',
            'description' => 'Монгол тайлбар'
        ]
    ]
]
```

---

#### `getRowWhere(array $with_values): array|null`

WHERE key=value хэлбэрийн нөхцлөөр мөр (олон хэлтэй) авах.

**Параметрүүд:**
- `$with_values` (array) - key => value массив

**Буцаах утга:** `getRow()`-ийн буцаах бүтэцтэй ижил; олдохгүй бол null

**Жич:** Энэ функц `getRow()`-ийг дуудаж байна

---

#### `getRowsByCode(string $code, array $condition = []): array`

Олон мөрийг тодорхой хэлний кодоор авах.

Энэ функц нь тухайн хэлний кодыг өгөхөд зөвхөн тухайн хэлний контентыг буцаана.

**Параметрүүд:**
- `$code` (string) - Хэлний код (жишээ: 'en', 'mn', 'ru')
- `$condition` (array) - SELECT нөхцөл (WHERE, JOIN, ORDER, LIMIT гэх мэт, code нөхцөл автоматаар нэмэгдэнэ)

**Буцаах утга:** Массив `[primary_id => rowStructure]`, rowStructure нь дараах бүтэцтэй:
- Primary хүснэгтийн бүх багана утгууд шууд түвшинд
- 'localized' түлхүүр дор зөвхөн тухайн хэлний контент (хэлний кодын түвшин байхгүй)

**Жишээ:** code='en' бол:
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

**Жич:** Бүх хэлний контентыг авах функц - `getRows()`, Нэг мөрийг авах функц - `getRow()`

---

## PDOTrait

**Namespace:** `codesaur\DataObject\PDOTrait`

**Тайлбар:** Энэ trait нь PDO дээр суурилсан өгөгдлийн баазын ерөнхий үйлдлүүдийг (prepare, query, exec, quote, driver төрлийг авах) нэг стандарт интерфэйс болгон төвлөрүүлдэг.

codesaur экосистемийн бүх Model болон Table-тэй холбоотой классууд энэ trait-ийг ашигласнаар:

- PDO instance-г хуваалцана
- SQL statement-үүдийг найдвартай бэлтгэнэ
- Алдааг стандарт хэлбэрээр шиднэ
- MySQL/PostgreSQL/SQLite драйверийг автоматаар танина
- FOREIGN KEY CHECKS зэрэг тохиргоог удирдана

**Trait**

### Properties

#### `protected \PDO $pdo`

PDO instance.

---

#### `private ?string $_driver`

Ашиглаж буй драйверийн нэр (mysql | pgsql | sqlite).

### Methods

#### `setInstance(\PDO $pdo): void`

PDO instance-г загварт оноож өгөх.

**Параметрүүд:**
- `$pdo` (\PDO) - PDO instance

---

#### `getDriverName(): string|null`

Ашиглаж буй PDO драйверийн нэрийг буцаана.

**Буцаах утга:** Драйверийн нэр (mysql, pgsql, sqlite)

---

#### `throwPdoError(string $message, \PDO|\PDOStatement $source): never`

PDO/PDOStatement-ийн алдааны мэдээллээр Exception шидэх.

**Параметрүүд:**
- `$message` (string) - Алдааны тайлбар
- `$source` (\PDO|\PDOStatement) - Алдааны эх үүсвэр

**Exception:** `Exception` - Драйверийн алдааны код болон мэдээлэлтэйгээр заавал шиднэ

---

#### `quote(string $string, int $parameter_type = \PDO::PARAM_STR): string|false`

SQL string-ийг драйверт тохирсон хэлбэрээр escape хийх.

**Параметрүүд:**
- `$string` (string) - SQL-д ашиглах текст
- `$parameter_type` (int) - PDO::PARAM_* төрөл

**Буцаах утга:** Escape хийгдсэн string, эсвэл false

---

#### `prepare(string $statement, array $driver_options = []): \PDOStatement`

SQL statement-г бэлтгэж (prepare) PDOStatement буцаана.
Амжилтгүй бол стандарт Exception шиднэ.

**Параметрүүд:**
- `$statement` (string) - Бэлтгэх SQL
- `$driver_options` (array) - PDO-ийн драйвер тохиргоо

**Буцаах утга:** `PDOStatement`

**Exception:** `Exception` - PDO алдаа гарвал

---

#### `exec(string $statement): int|false`

SQL команд шууд гүйцэтгэх (DDL/DML).

**Параметрүүд:**
- `$statement` (string) - SQL команд (CREATE, DROP, UPDATE гэх мэт)

**Буцаах утга:** Нөлөөлсөн мөрийн тоо буюу false

---

#### `query(string $statement): \PDOStatement`

Query-г бэлтгэлгүйгээр шууд гүйцэтгэх.

**Параметрүүд:**
- `$statement` (string) - SQL SELECT

**Буцаах утга:** `PDOStatement`

**Exception:** `Exception` - PDO алдаа гарвал

---

#### `hasTable(string $table): bool`

Өгөгдлийн баазад хүснэгт байгаа эсэхийг шалгах.

**Параметрүүд:**
- `$table` (string) - Хүснэгтийн нэр

**Буцаах утга:** Хүснэгт байгаа эсэх

**Exception:** `RuntimeException` - Дэмжээгүй драйвер

**Дэмжсэн драйверууд:**
- MySQL: `SHOW TABLES LIKE ...`
- PostgreSQL: `SELECT ... FROM pg_tables ...`
- SQLite: `SELECT ... FROM sqlite_master ...`

---

## TableTrait

**Namespace:** `codesaur\DataObject\TableTrait`

**Тайлбар:** Энэ trait нь өгөгдлийн сан дахь хүснэгттэй ажиллах үндсэн боломжуудыг бүрэн агуулдаг.

Үүнд:
- Хүснэгтийн нэр ба багануудын тодорхойлолт
- Хүснэгт автоматаар үүсгэх логик (MySQL/PostgreSQL/SQLite-д таарсан)
- PRIMARY, UNIQUE багана баталгаажуулалт
- CRUD-ийн туслах үйлдлүүд (deleteById, deactivateById)
- SELECT statement builder (JOIN, WHERE, LIMIT...)
- Хүснэгт байгаа эсэхийг шалгах

Энэ trait нь Model болон LocalizedModel-ийн үндсэн суурь юм.

**Trait**

**Uses:** `PDOTrait`

### Properties

#### `protected readonly string $name`

SQL хүснэгтийн нэр.

---

#### `protected readonly array $columns`

SQL хүснэгтийн багануудын тодорхойлолт.
Column объектуудын массив.

### Abstract Methods

#### `public abstract function __construct(\PDO $pdo)`

Загварын constructor - PDO заавал дамжина.

**Параметрүүд:**
- `$pdo` (\PDO) - PDO instance

---

#### `protected abstract function __initial(): void`

Хүснэгтийг бодит бааз дээр анх удаа CREATE шинээр үүсгэсний дараах анхны тохиргоо хийх.

### Methods

#### `__destruct(): void`

Destructor - PDO-г чөлөөлнө.

---

#### `getName(): string`

Хүснэгтийн нэр авах.

**Буцаах утга:** Хүснэгтийн нэр

**Exception:** `Exception` - Хүснэгтийн нэр тогтоогдоогүй бол

---

#### `setTable(string $name): void`

Хүснэгтийн нэрийг тогтоож хүснэгтийг үүсгэнэ.

Зөвшөөрөгдөх тэмдэгтээр filter хийж нэр өгнө.

**Параметрүүд:**
- `$name` (string) - Хүснэгтийн нэр

**Exception:** `Exception` - Баганууд тодорхойлогдоогүй эсвэл хүснэгт үүсгэхэд алдаа гарвал

---

#### `getColumns(): array`

Хүснэгтийн бүх багануудыг буцаах.

**Буцаах утга:** `Column[]` массив

**Exception:** `Exception` - Баганууд тодорхойлогдоогүй бол

---

#### `setColumns(array $columns): void`

Хүснэгтийн багануудыг Column объектуудаар баталгаажуулан тохируулах.

**Параметрүүд:**
- `$columns` (Column[]) - Column объектуудын массив

**Exception:** `Exception` - Column биш объект байвал

---

#### `getColumn(string $name): Column`

Нэрээр нь багана буцаах.

**Параметрүүд:**
- `$name` (string) - Баганын нэр

**Буцаах утга:** `Column` объект

**Exception:** `Exception` - Багана олдохгүй бол

---

#### `hasColumn(string $name): bool`

Нэртэй багана байгаа эсэх.

**Параметрүүд:**
- `$name` (string) - Баганын нэр

**Буцаах утга:** Багана байгаа эсэх

---

#### `deleteById(int $id): bool`

ID-р мөр устгах.

**Параметрүүд:**
- `$id` (int) - Устгах ID

**Буцаах утга:** Амжилттай эсэх

**Exception:** `Exception` - id багана primary auto increment биш бол

---

#### `deactivateById(int $id, array $record = []): bool`

ID-р мөрийг идэвхгүй болгох (soft delete). `is_active` баганыг 0 болгоно. UNIQUE баганууд өөрчлөгдөхгүй.

**Параметрүүд:**
- `$id` (int) - Идэвхгүй болгох ID
- `$record` (array) - Нэмэлт update талбарууд

**Буцаах утга:** Амжилттай эсэх

**Exception:** `Exception` - id багана primary auto increment биш эсвэл is_active багана байхгүй бол

---

#### `protected final function createTable(string $table, array $columns): void`

SQL хүснэгтийг үүсгэх (MySQL/PostgreSQL/SQLite-д тааруулах).

**Параметрүүд:**
- `$table` (string) - Хүснэгтийн нэр
- `$columns` (Column[]) - Багануудын массив

**Exception:** `Exception` - Хүснэгт үүсгэхэд алдаа гарвал

---

#### `selectStatement(string $fromTable, string $selection = '*', array $condition = []): \PDOStatement`

Уян хатан SELECT statement builder.

**Параметрүүд:**
- `$fromTable` (string) - FROM хүснэгтийн нэр
- `$selection` (string) - Сонгох баганууд ('*' эсвэл 'col1, col2, ...')
- `$condition` (array) - Нөхцлүүд:
  - `'JOIN'` / `'INNER JOIN'` / `'LEFT JOIN'` / `'RIGHT JOIN'` / `'CROSS JOIN'` - JOIN clause
  - `'WHERE'` - WHERE clause (жишээ: 'field=:param AND field2>:param2')
  - `'GROUP BY'` - GROUP BY clause
  - `'HAVING'` - HAVING clause
  - `'ORDER BY'` - ORDER BY clause
  - `'LIMIT'` - LIMIT clause
  - `'OFFSET'` - OFFSET clause
  - `'PARAM'` - Parameter массив [':param' => value, ...]

**Буцаах утга:** Prepared statement (`PDOStatement`)

**Exception:** `Exception` - SELECT гүйцэтгэхэд алдаа гарвал

**Жишээ:**
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

Column объектын SQL синтаксыг үүсгэх.
MySQL/PGSQL/SQLite-д тааруулж төрлийг хөрвүүлдэг.

**Параметрүүд:**
- `$column` (Column) - Column объект

**Буцаах утга:** SQL хэлбэр (жишээ: `id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY`)

**Төрөл хөрвүүлэлт:**
- **PostgreSQL:** int8->bigint, integer->int, tinyint->smallint, datetime->timestamp, tinytext->text, bigint+auto->bigserial, int+auto->serial
- **SQLite:** Бүх integer төрлүүд->INTEGER, decimal/float->REAL, blob->BLOB, бусад->TEXT
- **MySQL:** bigserial->bigint, serial->int, smallserial->smallint, timestamptz->timestamp

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
- Collation support

#### PostgreSQL
- `RETURNING *` clause for INSERT/UPDATE
- `serial`, `bigserial` for auto-increment
- `pg_tables` for table existence check

#### SQLite
- `AUTOINCREMENT` for auto-increment primary keys
- `sqlite_master` for table existence check
- Simplified type system (INTEGER, REAL, TEXT, BLOB)

---

## License

MIT License

---

## Author

Narankhuu
https://github.com/codesaur
