# 🧱 codesaur/dataobject  
**PDO суурьтай өгөгдлийн модель ба хүснэгтүүдийг удирдагч компонент (MySQL / PostgreSQL, PHP 8.2+)**

`codesaur/dataobject` нь **codesaur-php** экосистемийн өгөгдлийн давхаргын үндсэн компонент.  
Энгийн `PDO`-г шууд ашиглахын оронд:

- хүснэгтүүдийн бүтцийг **PHP класс дотор Column-оор тодорхойлж**,  
- хүснэгтийг **анх удаа ажиллах үедээ автоматаар үүсгэж**,  
- MySQL / PostgreSQL хоёр дээр **адилхан кодоор** ажиллах боломжийг олгодог.

Гол санаа нь:
> _“Schema + CRUD логикоороо давтагдсан бүх кодуудыг дахин ашиглагдах Model / LocalizedModel дотор нуух”_

---

## ⚙ Шаардлага

- PHP **8.2.1+**
- `ext-pdo`
- MySQL эсвэл PostgreSQL

---

## 📦 Суурилуулалт

```bash
composer require codesaur/dataobject
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
✔ MySQL / PostgreSQL ялгааг автоматаар зохицуулна

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

- `parent_id` → FK → primary.id  
- `code` → хэлний код (mn, en, jp …)  
- бусад талбарууд (`title`, `description`, …)

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
}
```
---

### `PDOTrait`

`PDOTrait` нь бодит `PDO` instance-тэй ажиллах бүх бага түвшний логикийг нэг дор төвлөрүүлсэн trait юм.

**Үндсэн боломжууд:**

- `setInstance(PDO $pdo)` - гаднаас PDO-г суулгаж өгнө  
- `getDriverName()` - `mysql`, `pgsql` гэх мэт драйверийн нэрийг кештэйгээр буцаана  
- `quote()`, `prepare()`, `exec()`, `query()` - PDO-гийн үндсэн функцуудын **safe wrapper**  
  - `prepare()` / `query()` нь `false` буцсан үед **алдааны Exception** шиднэ  
- `hasTable($name)` - хүснэгт байгаа эсэхийг MySQL / PostgreSQL хоёр дээр өөр SQL-аар шалгана  
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
  - MySQL / PostgreSQL хоёрын type mapping  
    - `serial`, `bigserial`, `timestamptz`, `tinyint` vs `smallint`, …  
  - PRIMARY, UNIQUE, AUTO_INCREMENT, DEFAULT, NULL/NOT NULL  
    бүгдийг **цэвэр SQL** болгон автоматаар угсарна

👉 Эцэст нь, `Model` / `LocalizedModel` нь **“зөвхөн баганаа зарлаад, setTable() дуудахад”** хүснэгт нь өөрөө үүсдэг.

---

## 🏃 Example Runner UI
Жишээ код моделиуд Example хавтсан дотор бүрэн эхээрээ орсон.

- `example/index.php` - MySQL/PostgreSQL сонгох UI

---

# 📄 Лиценз

Энэ төсөл MIT лицензтэй.

# 👨‍💻 Хөгжүүлэгч

Narankhuu  
📧 codesaur@gmail.com  
📱 +976 99000287  
🌐 https://github.com/codesaur  

# 🤝 Contribution

PR, issue, сайжруулалтын санаа байвал хүлээн авахад үргэлж нээлттэй.
Монгол хэл дээрх вэб системүүдийг цэвэр архитектуртай, дахин ашиглагдах кодтой болгох зорилготой тул:
- bug report
- feature request
- performance optimization
- нэмэлт DB driver (жишээ нь SQLite, SQL Server)

… бүгдийг GitHub Issues / PR хэлбэрээр илгээнэ үү.

“Clean data layer, minimal boilerplate - codesaur/dataobject.”

---

# 🎯 Дүгнэлт

`codesaur/dataobject` бол:

- Хүснэгт, баганыг **PHP кодоор тодорхойлдог**
- MySQL болон PostgreSQL дээр **адилхан кодоор ажилладаг**
- CRUD болон schema initialization-ийг **автоматаар шийдсэн**
- Өгөгдлийн давхаргыг **цэвэр, загварлаг** болгох  
- Хөнгөн, уян хатан, өргөтгөхөд хялбар компонент

PHP төсөлдөө **стандартчилсан, дахин ашиглагдах, цэвэр өгөгдлийн модель** ашиглахыг хүсвэл хамгийн зөв сонголт юм!

