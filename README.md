# 🧱 codesaur/dataobject  

[![CI](https://github.com/codesaur-php/DataObject/actions/workflows/ci.yml/badge.svg)](https://github.com/codesaur-php/DataObject/actions)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**🇬🇧 [English Version](README.EN.md) | 🇲🇳 Монгол Хувилбар**

---

**PDO суурьтай өгөгдлийн модель ба хүснэгтүүдийг удирдагч компонент (MySQL / PostgreSQL / SQLite, PHP 8.2.1+)**

`codesaur/dataobject` нь **codesaur-php** экосистемийн өгөгдлийн давхаргын үндсэн компонент.  
Энгийн `PDO`-г шууд ашиглахын оронд:

- хүснэгтүүдийн бүтцийг **PHP класс дотор Column-оор тодорхойлж**,  
- хүснэгтийг **анх удаа ажиллах үедээ автоматаар үүсгэж**,  
- MySQL / PostgreSQL / SQLite бүгд дээр **адилхан кодоор** ажиллах боломжийг олгодог.

Гол санаа нь:
> _“Schema + CRUD логикоороо давтагдсан бүх кодуудыг дахин ашиглагдах Model / LocalizedModel дотор нуух”_

---

## ⚙ Шаардлага

- PHP **8.2.1+**
- `ext-pdo`
- MySQL эсвэл PostgreSQL эсвэл SQLite

---

## 📦 Суурилуулалт

```bash
composer require codesaur/dataobject
```

## 🧪 Тест ажиллуулах

### Composer Test Command-ууд

```bash
# Бүх тест ажиллуулах (Unit + Integration тестүүд)
composer test

# HTML coverage report үүсгэх (coverage/ directory дотор)
composer test-coverage
```

### Command-уудын тайлбар

- **`composer test`** 
  - Бүх тестүүдийг (Unit болон Integration) ажиллуулна
  - PHPUnit ашиглан тестлэх
  - Тестүүдийн үр дүнг terminal дээр харуулна
  - Амжилттай/Амжилтгүй статусыг буцаана

- **`composer test-coverage`**
  - Бүх тестүүдийг ажиллуулна + code coverage report үүсгэнэ
  - HTML форматтай coverage report үүсгэнэ (`coverage/` directory)
  - Браузер дээр `coverage/index.html` файлыг нээж харж болно
  - Мөр, функц, класс тус бүрийн coverage хувийг харуулна

### Тестүүдийн мэдээлэл

- **Unit Tests**: Column, Model классуудын тест (18 тест, 42 assertion)
- **Integration Tests**: LocalizedModel-ийн бүрэн тест (7 тест, 48 assertion)
- **Нийт**: 25 тест, 90 assertion
- **Coverage**: 68.77% code coverage (447/650 lines)

### PHPUnit шууд ашиглах

Composer command-уудын оронд PHPUnit-ийг шууд ажиллуулж болно:

```bash
# Бүх тест ажиллуулах
vendor/bin/phpunit

# Зөвхөн Unit тестүүд
vendor/bin/phpunit tests/Unit

# Зөвхөн Integration тестүүд
vendor/bin/phpunit tests/Integration

# Coverage report (Clover XML формат)
vendor/bin/phpunit --coverage-clover coverage/clover.xml

# Coverage report (HTML формат)
vendor/bin/phpunit --coverage-html coverage
```

---

## 🧩 Гол классууд

# **Column**

Нэг баганын мета мэдээлэл:

- нэр (`name`)
- төрөл (`type` - int, varchar, datetime, …)
- урт (`length`)
- NULL / NOT NULL
- PRIMARY / UNIQUE / AUTO_INCREMENT
- анхны утга (`default`)

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

Энгийн (non-localized) хүснэгтийн суурь класс.

✔ Хүснэгтийн нэр, баганыг `setTable()` / `setColumns()`  
✔ CRUD: `insert()`, `updateById()`, `getRow()`, `getRows()`, `getRowWhere()`  
✔ `deleteById()`, `deactivateById()`  
✔ MySQL / PostgreSQL / SQLite ялгааг автоматаар зохицуулна

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
        // Хүснэгт анх удаа үүсгэгдэх үед ганц удаа ажиллана
    }
    
    // Жишээ: хэрэглэгч нэмэх
    public function createUser(string $username, string $hashedPassword): array|false
    {
        return $this->insert([
            'username'   => $username,
            'password'   => $hashedPassword,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Жишээ: хэрэглэгчийн идэвхжил шинэчлэх
    public function setActive(int $id, bool $active): array|false
    {
        return $this->updateById($id, [
            'is_active' => $active ? 1 : 0,
        ]);
    }
}
```
---

# **LocalizedModel**

Олон хэл дээр контент хадгалах шаардлагатай хүснэгтэд зориулагдсан суурь класс.

## Архитектур:

🔶 PRIMARY хүснэгт: `tablename`  
🔶 CONTENT хүснэгт: `tablename_content`  

CONTENT хүснэгт дотор:

- `parent_id` → FK → primary.id (CASCADE шинэчлэлт)  
- `code` → хэлний код (mn, en, jp …)  
- бусад талбарууд (`title`, `description`, …)

## Үндсэн функцүүд:

✔ **CRUD:** `insert($record, $content)`, `updateById($id, $record, $content)`  
✔ **Унших:** `getRow($condition)`, `getRows($condition)`, `getRowWhere($values)`, `getRowsByCode($code, $condition)`  
✔ MySQL / PostgreSQL / SQLite ялгааг автоматаар зохицуулна

## Буцаах утгын бүтэц:

`getRow()`, `getRows()`, `insert()`, `updateById()` функцүүд дараах бүтэцтэй массив буцаана:

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
            'title' => 'Монгол Гарчиг',
            'body' => 'Монгол агуулга...'
        ]
    ]
]
```

`getRowsByCode('en', $condition)` функц олон мөрийг зөвхөн тухайн хэлний кодоор буцаана (хэлний кодын түвшин байхгүй):

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
    
    // Жишээ: нийтлэл нэмэх (primary + localized)
    public function createArticle(string $slug, array $content): array|false
    {
        return $this->insert(
            [
                'slug'       => $slug,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            $content // ['en' => ['title' => '...', 'body' => '...'], 'mn' => [...]]
        );
    }
    
    // Жишээ: нийтлэлийн контентыг шинэчлэх
    public function updateArticle(int $id, array $content, array $record = []): array|false
    {
        return $this->updateById($id, $record, $content);
    }
    
    // Жишээ: тодорхой хэлээр нийтлэл авах
    public function getArticleByLang(int $id, string $lang): array|null
    {
        $rows = $this->getRowsByCode($lang, ['WHERE' => "p.id=$id"]);
        return $rows[$id] ?? null;
    }
    
    // Жишээ: тодорхой хэлээр бүх нийтлэлүүд авах
    public function getAllArticlesByLang(string $lang, bool $activeOnly = true): array
    {
        $condition = ['ORDER BY' => 'p.created_at DESC'];
        if ($activeOnly) {
            $condition['WHERE'] = 'p.is_active=1';
        }
        return $this->getRowsByCode($lang, $condition);
    }
    
    // Жишээ: slug болон хэлээр нийтлэл авах (WHERE + PARAM ашиглах)
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

`PDOTrait` нь бодит `PDO` instance-тэй ажиллах бүх бага түвшний логикийг нэг дор төвлөрүүлсэн trait юм.

**Үндсэн боломжууд:**

- `setInstance(PDO $pdo)` - гаднаас PDO-г суулгаж өгнө  
- `getDriverName()` - `mysql`, `pgsql`, `sqlite` гэх мэт драйверийн нэрийг кештэйгээр буцаана  
- `quote()`, `prepare()`, `exec()`, `query()` - PDO-гийн үндсэн функцуудын **safe wrapper**  
  - `prepare()` / `query()` нь `false` буцсан үед **алдааны Exception** шиднэ  
- `hasTable($name)` - хүснэгт байгаа эсэхийг MySQL / PostgreSQL / SQLite дээр тус бүр өөр SQL-аар шалгана  
- `setForeignKeyChecks(bool $enable)` - FK constraint-уудыг түр унтраах / асаах  
  - **MySQL:** `SET foreign_key_checks = 0|1`  
  - **PostgreSQL:** `SET session_replication_role = 'replica'|'origin'`

👉 Ингэснээр дээр нь суугаа `Model` / `LocalizedModel` нь **PDO код биш**, зөвхөн **бизнесс логик**-оо мэддэг болдог.

---

### `TableTrait`

`TableTrait` нь `PDOTrait`-ыг ашиглан **schema-level** ажиллагааг хариуцдаг:

- хүснэгтийн нэр (`$name`)  
- багануудын тодорхойлолт (`$columns`)  
- хүснэгт үүсгэх / шалгах / анхны өгөгдлөөр populate хийх  

**Гол үүргүүд:**

- `setColumns(array $columns)` - `Column` массивыг нэрээр нь индексжүүлж хадгална  
- `setTable(string $name)`  
  - хүснэгтийн нэрийг цэвэрлэнэ (`A-z 0-9 _-` ашиглана)  
  - баганууд зөв тодорхойлогдсон эсэхийг шалгана  
  - хүснэгт байхгүй бол → `createTable()` дуудаж **автоматаар үүсгэнэ**  
  - дараа нь моделийн `__initial()`-ийг **ганц удаа** ажиллуулна  
- `getColumns()` / `getColumn($name)` / `hasColumn($name)` - schema introspection  
- `deleteById($id)` - primary key ашиглан мөр устгана  
- `deactivateById($id, array $record = [])`  
  - `is_active` баганад `0` онооно  
  - UNIQUE давхардлаас сэргийлэх:  
    - numeric → утгыг **сөргөлдүүлнэ** (`-value`)  
    - string → `"[uniqid] original_value"` болгон өөрчилнө  
- `selectStatement($fromTable, $selection='*', array $condition=[])`  
  - JOIN / WHERE / GROUP BY / ORDER / LIMIT / OFFSET бүхнийг  
    ```php
    ['INNER JOIN' => '...', 'WHERE' => '...', 'PARAM' => [...]]
    ```  
    хэлбэрээр өгч, **динамик SELECT** үүсгэх боломж олгоно
- `createTable($table, array $columns)` / `getSyntax(Column $column)`  
  - MySQL / PostgreSQL / SQLite type mapping  
    - `serial`, `bigserial`, `timestamptz`, `tinyint` vs `smallint`, …  
  - PRIMARY, UNIQUE, AUTO_INCREMENT, DEFAULT, NULL/NOT NULL  
    бүгдийг **цэвэр SQL** болгон автоматаар угсарна

👉 Эцэст нь, `Model` / `LocalizedModel` нь **“зөвхөн баганаа зарлаад, setTable() дуудахад”** хүснэгт нь өөрөө үүсдэг.

---

## 🏃 Example Runner UI
Жишээ код моделиуд Example хавтсан дотор бүрэн эхээрээ орсон.

- `example/index.php` - MySQL/PostgreSQL/SQLite сонгох UI

## ✅ Тест ба CI/CD

Project нь бүрэн тестжүүлсэн:

- ✅ **PHPUnit** - Unit болон Integration тестүүд
- ✅ **GitHub Actions** - Автомат CI/CD pipeline
  - Push болон Pull Request үед автоматаар ажиллана
  - `main`, `master`, `develop` branch-ууд дээр trigger болно
- ✅ **Code Coverage** - 68.77% coverage (447/650 lines)
  - HTML coverage report: `coverage/` directory
  - Codecov дээр хадгална (Clover XML формат)
- ✅ **Multi-version** - PHP 8.2, 8.3 дээр тестлэгдсэн
- ✅ **Multi-OS** - Ubuntu, Windows дээр тестлэгдсэн
- ✅ **Database Extensions** - PDO, PDO_SQLite, PDO_MySQL, PDO_PgSQL суурилуулна

CI/CD workflow файл: `.github/workflows/ci.yml`

---

## 📚 Нэмэлт бичиг баримтууд

- **[CHANGELOG.md](CHANGELOG.md)** | **[CHANGELOG.EN.md](CHANGELOG.EN.md)** - Хувилбарын өөрчлөлтийн түүх
- **[API.md](API.md)** | **[API.EN.md](API.EN.md)** - Бүрэн API баримт бичиг (PHPDoc-уудаас Cursor AI ашиглан автоматаар үүсгэсэн)
- **[REVIEW.md](REVIEW.md)** | **[REVIEW.EN.md](REVIEW.EN.md)** - Code review үр дүн, олсон асуудлууд, сайжруулалтын саналууд (Cursor AI ашиглан үүсгэсэн)

---

# 📄 Лиценз

Энэ төсөл MIT лицензтэй.

# 👨‍💻 Зохиогч

**Narankhuu**  
📧 codesaur@gmail.com  
📲 [+976 99000287](https://wa.me/97699000287)  
🌐 https://github.com/codesaur  

# 🤝 Contribution

PR, issue, сайжруулалтын санаа байвал хүлээн авахад үргэлж нээлттэй.
Монгол хэл дээрх вэб системүүдийг цэвэр архитектуртай, дахин ашиглагдах кодтой болгох зорилготой тул:
- bug report
- feature request
- performance optimization
- нэмэлт DB driver (жишээ нь SQL Server)

… бүгдийг GitHub Issues / PR хэлбэрээр илгээнэ үү.

“Clean data layer, minimal boilerplate - codesaur/dataobject.”

---

# 🎯 Дүгнэлт

`codesaur/dataobject` бол:

- Хүснэгт, баганыг **PHP кодоор тодорхойлдог**
- MySQL / PostgreSQL / SQLite бүгд дээр **адилхан кодоор ажилладаг**
- CRUD болон schema initialization-ийг **автоматаар шийдсэн**
- Өгөгдлийн давхаргыг **цэвэр, загварлаг** болгох  
- Хөнгөн, уян хатан, өргөтгөхөд хялбар компонент

PHP төсөлдөө **стандартчилсан, дахин ашиглагдах, цэвэр өгөгдлийн модель** ашиглахыг хүсвэл хамгийн зөв сонголт юм!
