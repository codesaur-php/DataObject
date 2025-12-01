<?php

namespace codesaur\DataObject;

/**
 * Class LocalizedModel
 *
 * Олон хэл дээрх (localized) контент хадгалах зориулалттай
 * 2 хүснэгтийн загварын суурь класс.
 *
 * Архитектур:
 *   - Үндсэн хүснэгт (primary table) → id, is_active, sort гэх мэт universally shared баганууд
 *   - Контент хүснэгт (table_content) → олон хэл дээр хадгалах талбарууд
 *
 * Контент хүснэгт нь дараах бүтэцтэй:
 *   - id (primary)
 *   - parent_id (FK → primary table.id)
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
        $this->name = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);

        $table = $this->getName();
        $columns = $this->getColumns();

        // Баганууд заавал тодорхойлогдсон байх
        if (empty($columns) || empty($this->getContentColumns())) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", 1113);
        }

        // Primary table-д id багана байх ёстой
        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception(__CLASS__ . ": Table [$table] must have primary auto increment id column!");
        }

        // Хүснэгт аль хэдийн байвал хийх зүйлгүй
        if ($this->hasTable($table)) {
            return;
        }

        // Primary table үүсгэнэ
        $this->createTable($table, $columns);

        // Content table үүсгэнэ
        $contentTable = $this->getContentName();
        $this->createTable($contentTable, $this->getContentColumns());

        // FK тохиргоо
        $this->exec(
            "ALTER TABLE $contentTable 
             ADD FOREIGN KEY (parent_id) REFERENCES $table(id)
             ON DELETE CASCADE ON UPDATE CASCADE"
        );

        $this->__initial();
    }

    /**
     * Контент хүснэгтийн автоматаар угсрагдах нэр.
     *
     * @return string
     */
    public function getContentName(): string
    {
        return $this->getName() . '_content';
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
            1054
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
        $id = $this->getColumn('id');

        // Контент хүснэгтийн суурь баганууд
        $contentColumns = [
            'id' => (new Column('id', $id->getType()))->primary(),
            'parent_id' => (new Column('parent_id', $id->getType()))->notNull(),
            'code' => new Column('code', 'varchar', 2)
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
     * @param array $record Primary table-ийн өгөгдөл [column => value]
     * @param array $content ['mn'=>[col=>val], 'en'=>[col=>val], ...]
     * @return array|false Шинэ мөрийг нийлүүлсэн бүтэцтэй буцаана
     * @throws Exception
     */
    public function insert(array $record, array $content): array|false
    {
        $contentTable = $this->getContentName();

        if (empty($content)) {
            throw new \InvalidArgumentException(
                __CLASS__ . "[$contentTable}]: Can't insert record when localized content is empty!"
            );
        }

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

        if ($this->getDriverName() == 'pgsql') {
            $query .= ' RETURNING id';
        }

        $insert = $this->prepare($query);
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$insert->execute()) {
            return false;
        }

        // Шинэ ID
        if ($this->getDriverName() == 'pgsql') {
            $id = $insert->fetch(\PDO::FETCH_ASSOC)['id'];
        } else {
            $id = (int)($record['id'] ?? $this->pdo->lastInsertId('id'));
        }

        // Content table → олон хэл оруулах
        foreach ($content as $code => $data) {
            $content_field = $content_value = [];
            foreach (\array_keys($data) as $key) {
                $content_field[] = $key;
                $content_value[] = ":$key";
            }

            $data['parent_id'] = $id;
            $data['code'] = $code;

            $content_field[] = 'parent_id';
            $content_value[] = ':parent_id';

            $content_field[] = 'code';
            $content_value[] = ':code';

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
                    $error_info = $content_stmt->errorInfo();
                    $error_code = \is_numeric($error_info[1] ?? null)
                        ? (int)$error_info[1]
                        : (\is_numeric($content_stmt->errorCode())
                            ? (int)$content_stmt->errorCode()
                            : 0);

                    throw new \Exception(\implode(': ', $error_info), $error_code);
                }

            } catch (\Throwable $e) {
                $this->query("DELETE FROM $table WHERE id=$id");
                throw new \Exception(
                    __CLASS__ . ": Failed to insert content on table [$contentTable] " . $e->getMessage(),
                    $e->getCode()
                );
            }
        }

        return $this->getRowWhere(['p.id' => $id]) ?? false;
    }

    /**
     * Олон хэл дээрх контенттэй мөрийг id багана барьж шинэчлэх.
     *
     * @param int $id
     * @param array $record Primary table update [column => value]
     * @param array $content ['mn'=>[col=>val], 'en'=>[col=>val], ...]
     * @return array|false
     * @throws Exception
     */
    public function updateById(int $id, array $record, array $content): array|false
    {
        $table = $this->getName();

        $row = $current_record = $this->getRowWhere(['p.id' => $id]) ?? [];
        // Primary table шинэчлэлт
        if (!empty($record)) {
            $set = [];
            foreach (\array_keys($record) as $name) {
                $set[] = "$name=:$name";
            }

            $sets = \implode(', ', $set);
            $update_primary = $this->prepare("UPDATE $table SET $sets WHERE id=:old_id");

            $update_primary->bindValue(':old_id', $id, \PDO::PARAM_INT);

            foreach ($record as $name => $value) {
                $update_primary->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
                $row[$name] = $value;
            }

            if (!$update_primary->execute()) {
                return false;
            }
        } elseif (empty($content)) {
            throw new \Exception(__CLASS__ . ': Failed to update by id! No data provided!');
        }

        // Content table шинэчлэх
        $contentTable = $this->getContentName();
        $content_select = $this->prepare(
            "SELECT id FROM $contentTable WHERE parent_id=:parent_id AND code=:code LIMIT 1"
        );

        $parent_id = $record['id'] ?? $id;

        foreach ($content as $code => $value) {
            foreach (\array_keys($value) as $key) {
                if ($key === 'parent_id' || $key === 'code') {
                    unset($value[$key]);
                }
            }

            if (empty($value)) {
                continue;
            }

            try {
                // Хэлний content мөр байгаа эсэхийг шалгах
                $content_select->bindValue(':parent_id', $parent_id, \PDO::PARAM_INT);
                $content_select->bindValue(':code', $code, \PDO::PARAM_STR);

                if (!$content_select->execute()
                    || $content_select->rowCount() < 1
                ) {
                    // Байхгүй → шинээр INSERT
                    $column = ['code'];
                    $param = [':code'];

                    foreach (\array_keys($value) as $key) {
                        $column[] = $key;
                        $param[] = ":$key";
                    }

                    $columns = \implode(', ', $column);
                    $values = \implode(', ', $param);

                    $content_stmt = $this->prepare(
                        "INSERT INTO $contentTable(parent_id,$columns) VALUES($parent_id,$values)"
                    );

                    $content_stmt->bindValue(":code", $code, \PDO::PARAM_STR);

                    foreach ($value as $key => $var) {
                        $content_stmt->bindValue(":$key", $var, $this->getContentColumn($key)->getDataType());
                        $row['localized'][$key][$code] = $var;
                    }

                } else {
                    // Байгаа → UPDATE хийнэ
                    $content_row = $content_select->fetch(\PDO::FETCH_ASSOC);

                    $content_set = [];
                    foreach (\array_keys($value) as $n) {
                        $content_set[] = "$n=:$n";
                    }

                    $content_sets = \implode(', ', $content_set);
                    $content_stmt = $this->prepare(
                        "UPDATE $contentTable SET $content_sets WHERE id={$content_row['id']}"
                    );

                    foreach ($value as $key => $var) {
                        $content_stmt->bindValue(":$key", $var, $this->getContentColumn($key)->getDataType());
                        $row['localized'][$key][$code] = $var;
                    }
                }

                if (!$content_stmt->execute()) {
                    $error_info = $content_stmt->errorInfo();
                    $error_code = \is_numeric($error_info[1] ?? null)
                        ? (int)$error_info[1]
                        : (\is_numeric($content_stmt->errorCode())
                            ? (int)$content_stmt->errorCode()
                            : 0);

                    throw new \Exception(\implode(': ', $error_info), $error_code);
                }

            } catch (\Throwable $e) {
                throw new \Exception(
                    __CLASS__ . ": Failed to update content on table [$contentTable]! "
                    . $e->getMessage(),
                    $e->getCode()
                );
            }
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
        if ($selection === '*') {
            $fields = [];

            foreach (\array_keys($this->getColumns()) as $column) {
                $fields[] = "p.$column as p_$column";
            }

            foreach (\array_keys($this->getContentColumns()) as $column) {
                $fields[] = "c.$column as c_$column";
            }

            $selection = \implode(', ', $fields);
        }

        $table = $this->getName();
        $contentTable = $this->getContentName();

        $condition['INNER JOIN'] = "$contentTable c ON p.id=c.parent_id";

        return $this->selectStatement("$table p", $selection, $condition);
    }

    /**
     * Олон мөрийг (олон хэлтэй) авах.
     *
     * @param array $condition
     * @return array
     */
    public function getRows(array $condition = []): array
    {
        $rows = [];
        $pdostmt = $this->select('*', $condition);

        $content_KeyColumns = ['id', 'parent_id', 'code'];

        while ($data = $pdostmt->fetch(\PDO::FETCH_ASSOC)) {
            $p_id = (int)$data['p_id'];

            // Primary утгуудыг нэгтгэх
            if (!isset($rows[$p_id]['id'])) {
                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();
                    $rows[$p_id][$columnName] = $data["p_$columnName"];
                }
            }

            // Localized утгуудыг нэгтгэх
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();

                if (!\in_array($ccolumnName, $content_KeyColumns)) {
                    $rows[$p_id]['localized'][$ccolumnName][$data['c_code']] =
                        $data["c_$ccolumnName"];
                }
            }
        }

        return $rows;
    }

    /**
     * Нэг мөрийг олон хэлтэйгээр авах.
     *
     * @param array $condition
     * @return array|null
     */
    public function getRow(array $condition): array|null
    {
        $row = [];
        $c_codeName = 'c_code';
        $content_KeyColumns = ['id', 'parent_id', 'code'];

        $stmt = $this->select('*', $condition);

        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Primary
            foreach ($this->getColumns() as $column) {
                $columnName = $column->getName();

                if (isset($data["p_$columnName"])) {
                    $row[$columnName] = $data["p_$columnName"];
                }
            }

            // Localized
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();

                if (!\in_array($ccolumnName, $content_KeyColumns)) {
                    if (isset($data["c_$ccolumnName"])) {
                        $row['localized'][$ccolumnName][$data[$c_codeName]] =
                            $data["c_$ccolumnName"];
                    }
                }
            }
        }

        return !empty($row) ? $row : null;
    }

    /**
     * WHERE key=value хэлбэрийн нөхцлөөр мөр (олон хэлтэй) авах.
     *
     * @param array $with_values
     * @return array|null
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
}
