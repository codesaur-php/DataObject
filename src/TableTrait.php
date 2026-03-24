<?php

namespace codesaur\DataObject;

/**
 * Trait TableTrait
 *
 * Энэ trait нь өгөгдлийн сан дахь хүснэгттэй ажиллах
 * үндсэн боломжуудыг бүрэн агуулдаг.
 *
 * Үүнд:
 *  - Хүснэгтийн нэр ба багануудын тодорхойлолт
 *  - Хүснэгт автоматаар үүсгэх логик (MySQL/PostgreSQL/SQLite-д таарсан)
 *  - PRIMARY, UNIQUE багана баталгаажуулалт
 *  - CRUD-ийн туслах үйлдлүүд (deleteById, deactivateById)
 *  - SELECT statement builder (JOIN, WHERE, LIMIT...)
 *  - Хүснэгт байгаа эсэхийг шалгах
 *
 * Энэ trait нь Model болон LocalizedModel-ийн үндсэн суурь юм.
 *
 * @package codesaur\DataObject
 */
trait TableTrait
{
    use PDOTrait;

    /**
     * SQL хүснэгтийн нэр.
     *
     * @var string
     */
    protected readonly string $name;

    /**
     * SQL хүснэгтийн багануудын тодорхойлолт.
     * Column объектуудын массив.
     *
     * @var Column[]
     */
    protected readonly array $columns;

    /**
     * Загварын constructor - PDO заавал дамжина.
     *
     * @param PDO $pdo
     */
    public abstract function __construct(\PDO $pdo);

    /**
     * Хүснэгтийг бодит бааз дээр анх удаа CREATE шинээр үүсгэсний дараах анхны тохиргоо хийх.
     *
     * @return void
     */
    protected abstract function __initial();

    /**
     * Destructor - PDO-г чөлөөлнө.
     */
    public function __destruct()
    {
        unset($this->pdo);
    }

    /**
     * Хүснэгтийн нэр авах.
     *
     * @return string
     * @throws \Exception
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            throw new \Exception(__CLASS__ . ': Table name must be provided', Constants::ERR_TABLE_NAME_MISSING);
        }

        return $this->name;
    }

    /**
     * Хүснэгтийн нэрийг тогтоож хүснэгтийг үүсгэнэ.
     *
     * @param string $name Зөвшөөрөгдөх тэмдэгтээр filter хийж нэр өгнө.
     * @return void
     * @throws \Exception
     */
    public function setTable(string $name)
    {
        // Хүснэгтийн нэрийг ариутган зөвшөөрөгдсөн тэмдэгтүүд үлдээнэ
        $this->name = \preg_replace(Constants::TABLE_NAME_PATTERN, '', $name);
        $table = $this->getName();

        // Багана тодорхойлогдоогүй бол алдаа
        if (empty($this->columns)) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", Constants::ERR_COLUMNS_NOT_DEFINED);
        }

        // Хүснэгт бааз дээр байвал дахин үүсгэхгүй
        if ($this->hasTable($table)) {
            return;
        }

        // Хүснэгтийг бааз дээр анхлан үүсгэнэ
        $this->createTable($table, $this->columns);

        // Хүснэгт шинээр үүссэний дараах тохиргоо
        $this->__initial();
    }

    /**
     * Хүснэгтийн бүх багануудыг буцаах.
     *
     * @return Column[]
     * @throws \Exception
     */
    public function getColumns(): array
    {
        return $this->columns
            ?? throw new \Exception("Table [$this->name] doesn't have columns definition!");
    }

    /**
     * Хүснэгтийн багануудыг Column объектуудаар баталгаажуулан тохируулах.
     *
     * @param Column[] $columns
     * @return void
     * @throws \Exception
     */
    public function setColumns(array $columns)
    {
        $columnSets = [];
        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new \Exception(__CLASS__ . ': Column should have been instance of Column class');
            }
            $columnSets[$column->getName()] = $column;
        }

        $this->columns = $columnSets;
    }

    /**
     * Нэрээр нь багана буцаах.
     *
     * @param string $name
     * @return Column
     * @throws \Exception
     */
    public function getColumn(string $name): Column
    {
        if ($this->hasColumn($name)) {
            return $this->columns[$name];
        }

        throw new \Exception(__CLASS__ . ": Table [$this->name] definition doesn't have column named [$name]", Constants::ERR_COLUMN_NOT_FOUND);
    }

    /**
     * Нэртэй багана байгаа эсэх.
     *
     * @param string $name
     * @return bool
     */
    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * ID-р мөр устгах.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteById(int $id): bool
    {
        $table = $this->getName();

        $col_id = Constants::COL_ID;
        if (!$this->hasColumn($col_id)
            || !$this->getColumn($col_id)->isInt()
            || !$this->getColumn($col_id)->isPrimary()
        ) {
            throw new \Exception("(deleteById): Table [$table] must have primary auto increment id column!");
        }

        $delete = $this->prepare("DELETE FROM $table WHERE $col_id=$id");
        return $delete->execute() && $delete->rowCount() > 0;
    }

    /**
     * ID-р мөрийг идэвхгүй болгох (soft delete).
     *
     * is_active баганыг 0 болгоно.
     * UNIQUE баганууд хэвээр үлдэнэ - ижил утгаар шинэ мөр нэмэх
     * шаардлага гарвал хуучин мөрийг delete хийх эсвэл бизнес логик дээр шийднэ.
     *
     * @param int $id
     * @param array $record Нэмэлт update талбарууд
     * @return bool
     * @throws \Exception Мөр олдохгүй, аль хэдийн идэвхгүй, эсвэл update амжилтгүй бол
     */
    public function deactivateById(int $id, array $record = []): bool
    {
        $table = $this->getName();

        // id багана заавал байх
        $col_id = Constants::COL_ID;
        if (!$this->hasColumn($col_id)
            || !$this->getColumn($col_id)->isInt()
            || !$this->getColumn($col_id)->isPrimary()
        ) {
            throw new \Exception("(deactivateById): Table [$table] must have primary auto increment id column!");
        }

        // is_active багана заавал байх
        $col_active = Constants::COL_IS_ACTIVE;
        if (!$this->hasColumn($col_active)
            || !$this->getColumn($col_active)->isInt()
        ) {
            throw new \Exception("(deactivateById): Table [$table] must have an is_active column!");
        }

        // Мөр унших
        $select = $this->query("SELECT $col_active FROM $table WHERE $col_id=$id");
        $row = $select->fetch(\PDO::FETCH_ASSOC);

        if (($row[$col_active] ?? 0) == 0) {
            throw new \Exception("(deactivateById): Row id=$id in table [$table] is already inactive!");
        }

        // UPDATE statement
        $set = ["$col_active=:$col_active"];
        foreach (\array_keys($record) as $name) {
            $set[] = "$name=:$name";
        }
        $sets = \implode(', ', $set);
        $update = $this->prepare("UPDATE $table SET $sets WHERE $col_id=$id");

        // is_active=0 болгоно
        $update->bindValue(":$col_active", 0, \PDO::PARAM_INT);

        // Нэмэлт баганууд
        foreach ($record as $name => $value) {
            $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }

        if (!$update->execute()) {
            $this->throwPdoError(__CLASS__ . ": Deactivate failed on [$table] for id=$id! ", $update);
        }

        return $update->rowCount() > 0;
    }

    /**
     * SQL хүснэгтийг үүсгэх (MySQL/PostgreSQL/SQLite).
     *
     * @param string $table
     * @param Column[] $columns
     * @return void
     * @throws \Exception
     */
    protected final function createTable(string $table, array $columns)
    {
        $references = [];
        $columnSyntaxes = [];
        // Багана бүрийн SQL синтакс бэлтгэх
        foreach ($columns as $key => $column) {
            $columnSyntaxes[] = $this->getSyntax($column);

            if ($column->isUnique()) {
                $references[] = "UNIQUE ($key)";
            }
        }

        // CREATE TABLE угсрах
        $create = "CREATE TABLE $table (" . \implode(', ', $columnSyntaxes);
        if (!empty($references)) {
            $create .= ', ' . \implode(', ', $references);
        }
        $create .= ')';

        // MySQL -> Collation тохируулах
        if ($this->getDriverName() == Constants::DRIVER_MYSQL) {
            $stmt = $this->query('SELECT @@collation_connection, @@collation_connection;');
            $collation = $stmt->fetchColumn();
            $create .= " ENGINE=InnoDB COLLATE=$collation";
        }

        // Гүйцэтгэх
        if ($this->exec($create) === false) {
            $this->throwPdoError(__CLASS__ . ": Table [$table] creation failed! ", $this->pdo);
        }
    }

    /**
     * Уян хатан SELECT statement builder.
     *
     * @param string $fromTable FROM хүснэгтийн нэр
     * @param string $selection Сонгох баганууд ('*' эсвэл 'col1, col2, ...')
     * @param array $condition Нөхцлүүд:
     *   - 'JOIN' / 'INNER JOIN' / 'LEFT JOIN' / 'RIGHT JOIN' / 'CROSS JOIN' - JOIN clause
     *   - 'WHERE' - WHERE clause (жишээ: 'field=:param AND field2>:param2')
     *   - 'GROUP BY' - GROUP BY clause
     *   - 'HAVING' - HAVING clause
     *   - 'ORDER BY' - ORDER BY clause
     *   - 'LIMIT' - LIMIT clause
     *   - 'OFFSET' - OFFSET clause
     *   - 'PARAM' - Parameter массив [':param' => value, ...]
     * @return \PDOStatement Prepared statement
     * @throws \Exception
     */
    public function selectStatement(string $fromTable, string $selection = '*', array $condition = []): \PDOStatement
    {
        $select = "SELECT $selection FROM $fromTable";
        foreach ([
            'JOIN', 'CROSS JOIN', 'INNER JOIN', 'LEFT JOIN',
            'RIGHT JOIN', 'WHERE', 'GROUP BY', 'HAVING',
            'ORDER BY', 'LIMIT', 'OFFSET'
        ] as $clause) {
            if (!empty($condition[$clause])) {
                $select .= ' ' . $clause . ' ' . $condition[$clause];
            }
        }
        $stmt = $this->prepare($select);
        if ($stmt->execute($condition['PARAM'] ?? null)) {
            return $stmt;
        }

        $this->throwPdoError(__CLASS__ . ": Can't select from [$fromTable]! ", $stmt);
    }

    /**
     * Column объектын SQL синтаксыг үүсгэх.
     * MySQL/PGSQL/SQLite-д тааруулж төрлийг хөрвүүлдэг.
     *
     * @param Column $column
     * @return string SQL хэлбэр
     */
    protected function getSyntax(Column $column): string
    {
        $str = $column->getName();

        // PRIMARY -> NOT NULL + AUTO
        if ($column->isPrimary()) {
            $column->notNull()->auto();
        }

        $type = $column->getType();
        $driver = $this->getDriverName();

        // PostgreSQL төрөл хөрвүүлэлт
        if ($driver == Constants::DRIVER_PGSQL) {
            switch ($type) {
                case 'int8': $type = 'bigint'; break;
                case 'integer':
                case 'mediumint': $type = 'int'; break;
                case 'tinyint': $type = 'smallint'; break;
                case 'datetime': $type = 'timestamp'; break;

                case 'tinytext':
                case 'mediumtext':
                case 'longtext': $type = 'text'; break;
            }

            if ($column->isAuto()) {
                if ($type === 'bigint') $type = 'bigserial';
                elseif ($type === 'int') $type = 'serial';
                elseif ($type === 'smallint') $type = 'smallserial';
            }
        } elseif ($driver == Constants::DRIVER_SQLITE) {
            // SQLite төрөл хөрвүүлэлт
            switch ($type) {
                case 'bigint':
                case 'int8':
                case 'int':
                case 'integer':
                case 'mediumint':
                case 'smallint':
                case 'tinyint':
                case 'serial':
                case 'bigserial':
                case 'smallserial':
                case 'bool':
                case 'boolean':
                    $type = 'INTEGER';
                    break;
                case 'decimal':
                case 'numeric':
                case 'float':
                case 'double':
                case 'real':
                    $type = 'REAL';
                    break;
                case 'blob':
                case 'tinyblob':
                case 'mediumblob':
                case 'longblob':
                case 'binary':
                case 'varbinary':
                    $type = 'BLOB';
                    break;
                default:
                    $type = 'TEXT';
            }
        } else { // MySQL хөрвүүлэлт
            switch ($type) {
                case 'bigserial': $type = 'bigint'; break;
                case 'serial': $type = 'int'; break;
                case 'smallserial': $type = 'smallint'; break;
                case 'timestamptz': $type = 'timestamp'; break;
            }
        }
        $str .= " $type";

        // Урт (SQLite дээр урт шаардлагагүй)
        if (!empty($column->getLength()) && $driver != Constants::DRIVER_SQLITE) {
            $str .= '(' . $column->getLength() . ')';
        }

        // NULL / NOT NULL
        $str .= $column->isNull() ? ' NULL' : ' NOT NULL';

        // DEFAULT
        $default = $column->getDefault();
        if ($default !== null) {
            $str .= ' DEFAULT ';
            if ($column->isNumeric()) {
                $str .= $default;
            } else {
                $str .= $this->quote($default);
            }
        }

        // PRIMARY KEY
        if ($column->isPrimary()) {
            $str .= ' PRIMARY KEY';
        }

        // AUTO_INCREMENT / AUTOINCREMENT
        if ($column->isAuto()) {
            if ($driver == Constants::DRIVER_MYSQL) {
                $str .= ' AUTO_INCREMENT';
            } elseif ($driver == Constants::DRIVER_SQLITE && $column->isPrimary()) {
                $str .= ' AUTOINCREMENT';
            }
        }

        return $str;
    }
}
