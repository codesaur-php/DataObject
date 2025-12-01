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
 *  - Хүснэгт автоматаар үүсгэх логик (MySQL/PostgreSQL-д таарсан)
 *  - PRIMARY, UNIQUE багана баталгаажуулалт
 *  - CRUD-ийн туслах үйлдлүүд (deleteById, deactivateById)
 *  - SELECT statement builder (JOIN, WHERE, LIMIT…)
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
     * Загварын constructor – PDO заавал дамжина.
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
     * Destructor – PDO-г чөлөөлнө.
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
            throw new \Exception(__CLASS__ . ': Table name must be provided', 1103);
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
        $this->name = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        $table = $this->getName();

        // Багана тодорхойлогдоогүй бол алдаа
        if (empty($this->columns)) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", 1113);
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

        throw new \Exception(__CLASS__ . ": Table [$this->name] definition doesn't have column named [$name]", 1054);
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

        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception("(deleteById): Table [$table] must have primary auto increment id column!");
        }

        $delete = $this->prepare("DELETE FROM $table WHERE id=$id");
        return $delete->execute() && $delete->rowCount() > 0;
    }

    /**
     * ID-р мөрийг идэвхгүй болгох (soft delete).
     *
     * UNIQUE багануудын утгыг зөрчилгүй болгохын тулд дараах арга хэрэглэнэ:
     *  - Тоон unique → -value болгон хөрвүүлнэ
     *  - Текстэн unique → [uniqid] prefix нэмнэ
     *
     * @param int $id
     * @param array $record Нэмэлт update талбарууд
     * @return bool
     * @throws \Exception
     */
    public function deactivateById(int $id, array $record = []): bool
    {
        $table = $this->getName();

        // id багана заавал байх
        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception("(deactivateById): Table [$table] must have primary auto increment id column!");
        }

        // is_active багана заавал байх
        if (!$this->hasColumn('is_active')
            || !$this->getColumn('is_active')->isInt()
        ) {
            throw new \Exception("(deactivateById): Table [$table] must have an is_active column!");
        }

        // SELECT хийх баганууд
        $selection = 'is_active';
        $set = ['is_active=:is_active'];
        $uniques = [];

        // UNIQUE багануудыг цуглуулах
        foreach ($this->getColumns() as $column) {
            $uniqueName = $column->getName();
            if ($column->isUnique() && $uniqueName !== 'id') {
                $uniques[] = $column;
                $selection .= ", $uniqueName";
                $set[] = "$uniqueName=:$uniqueName";
            }
        }

        // Нэмэлт update талбарууд
        foreach (\array_keys($record) as $name) {
            $selection .= ", $name";
            $set[] = "$name=:$name";
        }

        // Мөр унших
        $select = $this->query("SELECT $selection FROM $table WHERE id=$id");
        $row = $select->fetch(\PDO::FETCH_ASSOC);

        if (($row['is_active'] ?? 0) == 0) {
            return false;
        }

        // UPDATE statement
        $sets = \implode(', ', $set);
        $update = $this->prepare("UPDATE $table SET $sets WHERE id=$id");

        // is_active=0 болгоно
        $update->bindValue(':is_active', 0, \PDO::PARAM_INT);

        // UNIQUE багануудыг зөрчилгүй болгох
        foreach ($uniques as $unique) {
            $uniqueName = $unique->getName();
            if ($unique->isNumeric()) {
                $row[$uniqueName] = -$row[$uniqueName];
            } else {
                $row[$uniqueName] = '[' . \uniqid() . '] ' . $row[$uniqueName];
            }
            $update->bindValue(":$uniqueName", $row[$uniqueName], $unique->getDataType());
        }

        // Нэмэлт баганууд
        foreach ($record as $name => $value) {
            $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }

        return $update->execute() && $update->rowCount() > 0;
    }

    /**
     * SQL хүснэгтийг үүсгэх (MySQL/PostgreSQL-д тааруулах).
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
            if (!$column instanceof Column) {
                continue;
            }

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

        // MySQL → Collation тохируулах
        if ($this->getDriverName() == 'mysql') {
            $stmt = $this->query('SELECT @@collation_connection, @@collation_connection;');
            $collation = $stmt->fetchColumn();
            $create .= " ENGINE=InnoDB COLLATE=$collation";
        }

        // Гүйцэтгэх
        if ($this->exec($create) === false) {
            $error_info = $this->pdo->errorInfo();
            $error_code = \is_numeric($error_info[1] ?? null)
                ? (int)$error_info[1]
                : (\is_numeric($this->pdo->errorCode())
                    ? (int)$this->pdo->errorCode()
                    : 0);

            throw new \Exception(__CLASS__ . ": Table [$table] creation failed! "
                . \implode(': ', $error_info), $error_code);
        }
    }

    /**
     * Уян хатан SELECT statement builder.
     *
     * @param string $fromTable
     * @param string $selection
     * @param array $condition JOIN/WHERE/GROUP/LIMIT зэрэг нөхцлүүд
     * @return PDOStatement
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

        $error_info = $stmt->errorInfo();
        $error_code = \is_numeric($error_info[1] ?? null)
            ? (int)$error_info[1]
            : (\is_numeric($stmt->errorCode())
                ? (int)$stmt->errorCode()
                : 0);

        throw new \Exception(__CLASS__ . ": Can't select from [$fromTable]! "
            . \implode(': ', $error_info), $error_code);
    }

    /**
     * Column объектын SQL синтаксыг үүсгэх.
     * MySQL/PGSQL-д тааруулж төрлийг хөрвүүлдэг.
     *
     * @param Column $column
     * @return string SQL хэлбэр
     */
    private function getSyntax(Column $column): string
    {
        $str = $column->getName();

        // PRIMARY → NOT NULL + AUTO
        if ($column->isPrimary()) {
            $column->notNull()->auto();
        }

        $type = $column->getType();
        // PostgreSQL төрөл хөрвүүлэлт
        if ($this->getDriverName() == 'pgsql') {
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

        } else { // MySQL хөрвүүлэлт
            switch ($type) {
                case 'bigserial': $type = 'bigint'; break;
                case 'serial': $type = 'int'; break;
                case 'smallserial': $type = 'smallint'; break;
                case 'timestamptz': $type = 'timestamp'; break;
            }
        }
        $str .= " $type";

        // Урт
        if (!empty($column->getLength())) {
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

        // AUTO_INCREMENT → зөвхөн MySQL
        if ($column->isAuto() && $this->getDriverName() == 'mysql') {
            $str .= ' AUTO_INCREMENT';
        }

        return $str;
    }
}
