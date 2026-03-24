<?php

namespace codesaur\DataObject;

/**
 * Class LocalizedModel
 *
 * Олон хэл дээрх (localized) контент хадгалах зориулалттай
 * 2 хүснэгтийн загварын суурь класс.
 *
 * Архитектур:
 *   - Үндсэн хүснэгт (primary table) -> id, is_active, keyword, category гэх мэт universally shared баганууд
 *   - Контент хүснэгт (table_content) -> title, content гэх мэт олон хэл дээр хадгалах талбарууд
 *
 * Контент хүснэгт нь дараах бүтэцтэй:
 *   - id (primary)
 *   - parent_id (FK -> primary table.id)
 *   - code (хэлний код, ж: 'en', 'mn', 'jp')
 *   - бусад тухайн хэл дээр хадгалах талбарууд
 *
 * Энэ классыг ашигласнаар:
 *   - localized insert/update-г бүрэн автоматжуулна
 *   - localized мөрүүдийг нэгтгэн unified array хэлбэрээр буцаана
 *   - кодын дахин бичлэг огт шаардлагагүй
 *
 * @package codesaur\DataObject
 */
abstract class LocalizedModel
{
    use TableTrait;

    /**
     * Контент хүснэгтийн баганууд.
     *
     * @var Column[]
     */
    protected readonly array $contentColumns;

    /**
     * Хүснэгтийн нэрийг тогтоож, үндсэн болон контент хүснэгтүүдийг үүсгэнэ.
     *
     * @param string $name
     * @return void
     * @throws Exception
     */
    public function setTable(string $name)
    {
        $this->name = \preg_replace(Constants::TABLE_NAME_PATTERN, '', $name);

        $table = $this->getName();
        $columns = $this->getColumns();

        // Баганууд заавал тодорхойлогдсон байх
        if (empty($columns) || empty($this->getContentColumns())) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", Constants::ERR_COLUMNS_NOT_DEFINED);
        }

        // Primary table-д id багана байх ёстой
        $col_id = Constants::COL_ID;
        if (!$this->hasColumn($col_id)
            || !$this->getColumn($col_id)->isInt()
            || !$this->getColumn($col_id)->isPrimary()
        ) {
            throw new \Exception(__CLASS__ . ": Table [$table] must have primary auto increment id column!");
        }

        // Хүснэгт аль хэдийн байвал хийх зүйлгүй
        if ($this->hasTable($table)) {
            return;
        }

        // Primary table үүсгэнэ
        $this->createTable($table, $columns);

        // Content table үүсгэе
        $contentTable = $this->getContentName();

        // SQLite дээр FK-г CREATE TABLE-д шууд нэмэх хэрэгтэй
        if ($this->getDriverName() == Constants::DRIVER_SQLITE) {
            $contentColumns = $this->getContentColumns();
            $columnSyntaxes = [];
            $references = [];
            foreach ($contentColumns as $key => $column) {
                $columnSyntaxes[] = $this->getSyntax($column);
                if ($column->isUnique()) {
                    $references[] = "UNIQUE ($key)";
                }
            }
            // FK constraint нэмэх
            $col_parent = Constants::COL_PARENT_ID;
            $references[] = "FOREIGN KEY ($col_parent) REFERENCES $table($col_id) ON DELETE CASCADE ON UPDATE CASCADE";
            $create = "CREATE TABLE $contentTable (" . \implode(', ', $columnSyntaxes);
            if (!empty($references)) {
                $create .= ', ' . \implode(', ', $references);
            }
            $create .= ')';
            if ($this->exec($create) === false) {
                $this->throwPdoError(__CLASS__ . ": Table [$contentTable] creation failed! ", $this->pdo);
            }
        } else {
            $this->createTable($contentTable, $this->getContentColumns());

            // FK тохиргоо: parent_id -> primary.id, CASCADE шинэчлэлт
            $col_parent = Constants::COL_PARENT_ID;
            $this->exec(
                "ALTER TABLE $contentTable
                 ADD FOREIGN KEY ($col_parent) REFERENCES $table($col_id)
                 ON DELETE CASCADE ON UPDATE CASCADE"
            );
        }

        $this->__initial();
    }

    /**
     * Контент хүснэгтийн автоматаар угсрагдах нэр.
     *
     * @return string
     */
    public function getContentName(): string
    {
        return $this->getName() . Constants::CONTENT_TABLE_SUFFIX;
    }

    /**
     * Контент хүснэгтийн багануудыг авах.
     *
     * @return Column[]
     */
    public function getContentColumns(): array
    {
        return $this->contentColumns ?? [];
    }

    /**
     * Контент хүснэгтийн тодорхой багана авах.
     *
     * @param string $name
     * @return Column
     * @throws Exception
     */
    public function getContentColumn(string $name): Column
    {
        if (isset($this->contentColumns[$name])
            && $this->contentColumns[$name] instanceof Column
        ) {
            return $this->contentColumns[$name];
        }

        throw new \Exception(
            __CLASS__ . ": Table [{$this->getContentName()}] definition doesn't have localized content column named [$name]",
            Constants::ERR_COLUMN_NOT_FOUND
        );
    }

    /**
     * Контент хүснэгтийн багануудыг тогтоох.
     *
     * @param Column[] $columns
     * @return void
     * @throws Exception
     */
    public function setContentColumns(array $columns)
    {
        $col_id = Constants::COL_ID;
        $col_parent = Constants::COL_PARENT_ID;
        $col_code = Constants::COL_CODE;
        $id = $this->getColumn($col_id);

        // Контент хүснэгтийн суурь баганууд
        $contentColumns = [
            $col_id => (new Column($col_id, $id->getType()))->primary(),
            $col_parent => (new Column($col_parent, $id->getType()))->notNull(),
            $col_code => new Column($col_code, 'varchar', Constants::DEFAULT_CODE_LENGTH)
        ];

        // Хэрэглэгчийн оруулсан баганууд
        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new \Exception(__CLASS__ . ': Column should have been instance of Column class!');
            }

            if (isset($contentColumns[$column->getName()])) {
                throw new \Exception(__CLASS__ . ": Content table already has predefined column named [{$column->getName()}]");
            }

            if ($column->isUnique()) {
                throw new \Exception(__CLASS__ . ": Content table forbidden to contain unique column [{$column->getName()}]");
            }

            $contentColumns[$column->getName()] = $column;
        }

        $this->contentColumns = $contentColumns;
    }

    /**
     * Олон хэл дээрх контенттэй мөр нэмэх.
     *
     * @param array $record
     *   - Primary хүснэгтийн өгөгдөл: ['column' => value, ...]
     *   - Ж: ['name' => 'product', 'status' => 'active']
     * @param array $content
     *   - Хэлээр бүлэглэсэн контент: ['mn' => [col => val], 'en' => [...], ...]
     *   - Ж: ['en' => ['title' => 'English', 'description' => '...'], 'mn' => [...]]
     * @return array Шинэ мөрийг буцаана
     * @throws Exception
     * @see getRow() Буцаах утгын бүтэц
     */
    public function insert(array $record, array $content): array
    {
        $contentTable = $this->getContentName();

        if (empty($content)) {
            throw new \InvalidArgumentException(
                __CLASS__ . "[$contentTable]: Can't insert record when localized content is empty!"
            );
        }

        $driver = $this->getDriverName();

        // Primary table INSERT
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = \implode(', ', $column);
        $values = \implode(', ', $param);

        $table = $this->getName();
        $query = "INSERT INTO $table($columns) VALUES($values)";
        $col_id = Constants::COL_ID;
        if ($driver == Constants::DRIVER_PGSQL) {
            $query .= " RETURNING $col_id";
        }
        $insert = $this->prepare($query);
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$insert->execute()) {
            $this->throwPdoError(__CLASS__ . ": INSERT failed on [$table]! ", $insert);
        }

        // Шинэ ID
        if ($driver == Constants::DRIVER_PGSQL) {
            $id = $insert->fetch(\PDO::FETCH_ASSOC)[$col_id];
        } else {
            // SQLite дээр lastInsertId() нь sequence name шаардлагагүй
            $sequenceName = ($driver == Constants::DRIVER_SQLITE) ? null : $col_id;
            $id = (int)($record[$col_id] ?? $this->pdo->lastInsertId($sequenceName));
        }

        // Content table -> олон хэл оруулах
        foreach ($content as $code => $data) {
            $content_field = $content_value = [];
            foreach (\array_keys($data) as $key) {
                $content_field[] = $key;
                $content_value[] = ":$key";
            }

            $col_parent = Constants::COL_PARENT_ID;
            $col_code = Constants::COL_CODE;
            $data[$col_parent] = $id;
            $data[$col_code] = $code;

            $content_field[] = $col_parent;
            $content_value[] = ":$col_parent";

            $content_field[] = $col_code;
            $content_value[] = ":$col_code";

            $fields = \implode(', ', $content_field);
            $values = \implode(', ', $content_value);

            $content_stmt = $this->prepare(
                "INSERT INTO $contentTable($fields) VALUES($values)"
            );
            foreach ($data as $key => $value) {
                $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
            }

            try {
                if (!$content_stmt->execute()) {
                    $this->throwPdoError('', $content_stmt);
                }
            } catch (\Throwable $e) {
                $this->query("DELETE FROM $table WHERE $col_id=$id");
                throw new \Exception(
                    __CLASS__ . ": Failed to insert content on table [$contentTable] " . $e->getMessage(),
                    $e->getCode()
                );
            }
        }

        $row = $this->getRowWhere(["p.$col_id" => $id]);
        if ($row === null) {
            throw new \Exception(__CLASS__ . ": INSERT succeeded on [$table] but failed to retrieve the new row!");
        }
        return $row;
    }

    /**
     * Олон хэл дээрх контенттэй мөрийг id багана барьж шинэчлэх.
     *
     * @param int $id
     * @param array $record
     *   - Primary хүснэгтийн шинэчлэл: ['column' => value, ...]
     *   - Ж: ['read_count' => 10]
     * @param array $content
     *   - Хэлээр бүлэглэсэн контент шинэчлэл: ['mn' => [col => val], 'en' => [...], ...]
     *   - Ж: ['en' => ['title' => 'New title'], 'mn' => ['description' => 'Шинэ тайлбар']]
     * @return array Шинэчлэгдсэн мөрийг буцаана
     * @throws Exception
     * @see getRow() Буцаах утгын бүтэц
     */
    public function updateById(int $id, array $record, array $content): array
    {
        $table = $this->getName();

        // Primary table шинэчлэлт
        if (!empty($record)) {
            $set = [];
            foreach (\array_keys($record) as $name) {
                $set[] = "$name=:$name";
            }
            $sets = \implode(', ', $set);

            $col_id = Constants::COL_ID;
            $update_primary = $this->prepare("UPDATE $table SET $sets WHERE $col_id=:old_id");
            $update_primary->bindValue(':old_id', $id, \PDO::PARAM_INT);
            foreach ($record as $name => $value) {
                $update_primary->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
            }
            if (!$update_primary->execute()) {
                $this->throwPdoError(__CLASS__ . ": UPDATE failed on [$table] for id=$id! ", $update_primary);
            }
        } elseif (empty($content)) {
            throw new \Exception(__CLASS__ . ': Failed to update by id! No data provided!');
        }

        // Content table шинэчлэх
        $contentTable = $this->getContentName();
        $col_id = $col_id ?? Constants::COL_ID;
        $col_parent = Constants::COL_PARENT_ID;
        $col_code = Constants::COL_CODE;
        $content_select = $this->prepare(
            "SELECT $col_id FROM $contentTable WHERE $col_parent=:$col_parent AND $col_code=:$col_code LIMIT 1"
        );

        $parent_id = $record[$col_id] ?? $id;

        foreach ($content as $code => $value) {
            foreach (\array_keys($value) as $key) {
                if ($key === $col_parent || $key === $col_code) {
                    unset($value[$key]);
                }
            }
            if (empty($value)) {
                continue;
            }

            try {
                // Хэлний content мөр байгаа эсэхийг шалгах
                $content_select->bindValue(":$col_parent", $parent_id, \PDO::PARAM_INT);
                $content_select->bindValue(":$col_code", $code, \PDO::PARAM_STR);
                if (!$content_select->execute()
                    || $content_select->rowCount() < 1
                ) {
                    // Байхгүй -> шинээр INSERT
                    $column = [$col_code];
                    $param = [":$col_code"];
                    foreach (\array_keys($value) as $key) {
                        $column[] = $key;
                        $param[] = ":$key";
                    }
                    $columns = \implode(', ', $column);
                    $values = \implode(', ', $param);

                    $content_stmt = $this->prepare(
                        "INSERT INTO $contentTable($col_parent,$columns) VALUES($parent_id,$values)"
                    );
                    $content_stmt->bindValue(":$col_code", $code, \PDO::PARAM_STR);
                    foreach ($value as $key => $var) {
                        $content_stmt->bindValue(":$key", $var, $this->getContentColumn($key)->getDataType());
                    }
                } else {
                    // Байгаа -> UPDATE хийнэ
                    $content_row = $content_select->fetch(\PDO::FETCH_ASSOC);

                    $content_set = [];
                    foreach (\array_keys($value) as $n) {
                        $content_set[] = "$n=:$n";
                    }
                    $content_sets = \implode(', ', $content_set);
                    $content_stmt = $this->prepare(
                        "UPDATE $contentTable SET $content_sets WHERE $col_id={$content_row[$col_id]}"
                    );
                    foreach ($value as $key => $var) {
                        $content_stmt->bindValue(":$key", $var, $this->getContentColumn($key)->getDataType());
                    }
                }

                if (!$content_stmt->execute()) {
                    $this->throwPdoError('', $content_stmt);
                }

            } catch (\Throwable $e) {
                throw new \Exception(
                    __CLASS__ . ": Failed to update content on table [$contentTable]! "
                    . $e->getMessage(),
                    $e->getCode()
                );
            }
        }

        // Шинэчлэгдсэн мөрийг getRow-ийн бүтэцтэй буцаах
        $row = $this->getRowWhere(["p.$col_id" => $parent_id]);
        if ($row === null) {
            throw new \Exception(__CLASS__ . ": UPDATE succeeded on [$table] for id=$id but failed to retrieve the updated row!");
        }
        return $row;
    }

    /**
     * Хос хүснэгтээс (primary + content) JOIN хийж сонгох.
     *
     * @param string $selection
     * @param array $condition
     * @return PDOStatement
     */
    public function select(string $selection = '*', array $condition = []): \PDOStatement
    {
        $p_ = Constants::PRIMARY_ALIAS_PREFIX;
        $c_ = Constants::CONTENT_ALIAS_PREFIX;
        if ($selection === '*') {
            $fields = [];

            foreach (\array_keys($this->getColumns()) as $column) {
                $fields[] = "p.$column as $p_$column";
            }

            foreach (\array_keys($this->getContentColumns()) as $column) {
                $fields[] = "c.$column as $c_$column";
            }

            $selection = \implode(', ', $fields);
        }

        $table = $this->getName();
        $contentTable = $this->getContentName();

        $col_id = Constants::COL_ID;
        $col_parent = Constants::COL_PARENT_ID;
        $condition['INNER JOIN'] = "$contentTable c ON p.$col_id=c.$col_parent";

        return $this->selectStatement("$table p", $selection, $condition);
    }

    /**
     * Нөхцөлд тохирох мөрийн тоог буцаах.
     *
     * Primary хүснэгт дээр COUNT хийнэ (content JOIN шаардлагагүй).
     * WHERE нөхцөлд 'p.' prefix ашиглах шаардлагагүй.
     *
     * @param array $condition WHERE гэх мэт нөхцөл
     * @return int
     */
    public function countRows(array $condition = []): int
    {
        $stmt = $this->selectStatement($this->getName(), 'COUNT(*) as cnt', $condition);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Олон мөрийг (олон хэлтэй) авах.
     *
     * @param array $condition
     * @return array Массив [primary_id => rowStructure], rowStructure нь getRow-ийн буцаах бүтцийн адил
     *
     * @example
     *   [
     *     1 => [
     *       'id' => 1,
     *       'name' => 'product_name',
     *       'status' => 'active',
     *       'localized' => [
     *         'en' => [
     *           'title' => 'English Title',
     *           'description' => 'English Description'
     *         ],
     *         'mn' => [
     *           'title' => 'Монгол гарчиг',
     *           'description' => 'Монгол тайлбар'
     *         ]
     *       ]
     *     ],
     *     2 => [
     *       'id' => 2,
     *       'name' => 'another_product',
     *       'status' => 'draft',
     *       'localized' => [
     *         'en' => ['title' => 'Another', 'description' => 'Desc'],
     *         'mn' => ['title' => 'Өөр', 'description' => 'Тайлбар']
     *       ]
     *     ]
     *   ]
     */
    public function getRows(array $condition = []): array
    {
        $rows = [];
        $pdostmt = $this->select('*', $condition);

        $p_ = Constants::PRIMARY_ALIAS_PREFIX;
        $c_ = Constants::CONTENT_ALIAS_PREFIX;
        $col_id = Constants::COL_ID;
        $col_code = Constants::COL_CODE;
        $localized = Constants::LOCALIZED_KEY;

        while ($data = $pdostmt->fetch(\PDO::FETCH_ASSOC)) {
            $p_id = (int)$data["$p_$col_id"];

            // Primary утгуудыг нэгтгэх
            if (!isset($rows[$p_id][$col_id])) {
                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();
                    $rows[$p_id][$columnName] = $data["$p_$columnName"];
                }
            }

            // Localized утгуудыг нэгтгэх
            $langCode = $data["$c_$col_code"];
            if (!isset($rows[$p_id][$localized][$langCode])) {
                $rows[$p_id][$localized][$langCode] = [];
            }

            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();

                if (!\in_array($ccolumnName, Constants::CONTENT_KEY_COLUMNS)) {
                    if (isset($data["$c_$ccolumnName"])) {
                        $rows[$p_id][$localized][$langCode][$ccolumnName] = $data["$c_$ccolumnName"];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Нэг мөрийг олон хэлтэйгээр авах.
     *
     * @param array $condition SELECT нөхцөл (WHERE, JOIN, ORDER, LIMIT гэх мэт)
     * @return array|null Амжилттай бол дараах бүтэцтэй массив, олдохгүй бол null:
     *   - Primary хүснэгтийн бүх багана утгууд шууд түвшинд (жишээ: 'id', 'name', 'status' гэх мэт)
     *   - 'localized' түлхүүр дор олон хэлтэй контентууд:
     *     - Эхний түвшин: хэлний код (жишээ: 'en', 'mn', 'ru' гэх мэт)
     *     - Хоёрдугаар түвшин: контент баганын нэр (жишээ: 'title', 'description' гэх мэт)
     *     - Утга: тухайн хэл дээрх контентын утга
     *
     * @example
     *   [
     *     'id' => 1,
     *     'name' => 'product_name',
     *     'status' => 'active',
     *     'localized' => [
     *       'en' => [
     *         'title' => 'English Title',
     *         'description' => 'English Description'
     *       ],
     *       'mn' => [
     *         'title' => 'Монгол гарчиг',
     *         'description' => 'Монгол тайлбар'
     *       ]
     *     ]
     *   ]
     */
    public function getRow(array $condition): array|null
    {
        $row = [];
        $p_ = Constants::PRIMARY_ALIAS_PREFIX;
        $c_ = Constants::CONTENT_ALIAS_PREFIX;
        $col_code = Constants::COL_CODE;
        $localized = Constants::LOCALIZED_KEY;

        $stmt = $this->select('*', $condition);

        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Primary
            foreach ($this->getColumns() as $column) {
                $columnName = $column->getName();

                if (isset($data["$p_$columnName"])) {
                    $row[$columnName] = $data["$p_$columnName"];
                }
            }

            // Localized
            $langCode = $data["$c_$col_code"];
            if (!isset($row[$localized][$langCode])) {
                $row[$localized][$langCode] = [];
            }

            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();

                if (!\in_array($ccolumnName, Constants::CONTENT_KEY_COLUMNS)) {
                    if (isset($data["$c_$ccolumnName"])) {
                        $row[$localized][$langCode][$ccolumnName] = $data["$c_$ccolumnName"];
                    }
                }
            }
        }

        return !empty($row) ? $row : null;
    }

    /**
     * ID-р мөр байгаа эсэхийг шалгах.
     *
     * @param int $id
     * @return bool
     */
    public function existsById(int $id): bool
    {
        $table = $this->getName();
        $col_id = Constants::COL_ID;
        $stmt = $this->prepare("SELECT 1 FROM $table WHERE $col_id=:$col_id LIMIT 1");
        $stmt->execute([":$col_id" => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * ID-р мөр (олон хэлтэй) авах.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): array|null
    {
        return $this->getRowWhere(['p.' . Constants::COL_ID => $id]);
    }

    /**
     * WHERE key=value хэлбэрийн нөхцлөөр мөр (олон хэлтэй) авах.
     *
     * @param array $with_values
     * @return array|null getRow-ийн буцаах бүтцийн адил; олдохгүй бол null
     * @see getRow() Энэ функц getRow-ийг дуудаж байна
     */
    public function getRowWhere(array $with_values): array|null
    {
        $count = 1;
        $params = [];
        $wheres = [];
        foreach ($with_values as $key => $value) {
            $params[":$count"] = $value;
            $wheres[] = "$key=:$count";
            $count++;
        }
        $clause = \implode(' AND ', $wheres);
        if (empty($clause)) {
            return null;
        }
        return $this->getRow([
            'WHERE' => $clause,
            'PARAM' => $params
        ]);
    }

    /**
     * Олон мөрийг тодорхой хэлний кодоор авах.
     *
     * Энэ функц нь тухайн хэлний кодыг өгөхөд зөвхөн тухайн хэлний контентыг буцаана.
     *
     * @param string $code Хэлний код (жишээ: 'en', 'mn', 'ru')
     * @param array $condition SELECT нөхцөл (WHERE, JOIN, ORDER, LIMIT гэх мэт, code нөхцөл автоматаар нэмэгдэнэ)
     * @return array Массив [primary_id => rowStructure], rowStructure нь дараах бүтэцтэй:
     *   - Primary хүснэгтийн бүх багана утгууд шууд түвшинд
     *   - 'localized' түлхүүр дор зөвхөн тухайн хэлний контент (хэлний кодын түвшин байхгүй)
     *
     * @example code='en' бол:
     *   [
     *     1 => [
     *       'id' => 1,
     *       'name' => 'product_name',
     *       'status' => 'active',
     *       'localized' => [
     *         'title' => 'English Title',
     *         'description' => 'English Description'
     *       ]
     *     ],
     *     2 => [
     *       'id' => 2,
     *       'name' => 'another_product',
     *       'status' => 'draft',
     *       'localized' => [
     *         'title' => 'Another Title',
     *         'description' => 'Another Description'
     *       ]
     *     ]
     *   ]
     *
     * @see getRows() Бүх хэлний контентыг авах функц
     * @see getRow() Нэг мөрийг авах функц
     */
    public function getRowsByCode(string $code, array $condition = []): array
    {
        // WHERE clause-д хэлний кодыг нэмэх
        $col_code = Constants::COL_CODE;
        $existingWhere = $condition['WHERE'] ?? '';
        $codeWhere = "c.$col_code=:$col_code";

        if (!empty($existingWhere)) {
            $condition['WHERE'] = "($existingWhere) AND $codeWhere";
        } else {
            $condition['WHERE'] = $codeWhere;
        }

        // PARAM-д code нэмэх
        $existingParams = $condition['PARAM'] ?? [];
        $existingParams[":$col_code"] = $code;
        $condition['PARAM'] = $existingParams;

        // Бүх мөрийг авах
        $rows = $this->getRows($condition);

        // Зөвхөн тухайн хэлний контентыг буцаах
        $localized = Constants::LOCALIZED_KEY;
        $result = [];
        foreach ($rows as $p_id => $row) {
            if (isset($row[$localized][$code])) {
                $result[$p_id] = $row;
                $result[$p_id][$localized] = $row[$localized][$code];
            } else {
                // Хэлний контент байхгүй ч primary утгууд байвал буцаана
                $result[$p_id] = $row;
                $result[$p_id][$localized] = [];
            }
        }

        return $result;
    }
}
