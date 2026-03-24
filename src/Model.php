<?php

namespace codesaur\DataObject;

/**
 * Class Model
 *
 * DataObject экосистемийн үндсэн нэг хүснэгтэд зориулсан загварын (model) суурь класс.
 *
 * Энэ класс нь TableTrait-ийг ашиглан:
 *   - хүснэгтийн бүтцийг удирдах
 *   - хүснэгт үүсгэх / багана шалгах
 *   - өгөгдөл нэмэх (insert)
 *   - өгөгдөл шинэчлэх (updateById)
 *   - мөр авах (getRow, getRows)
 *   - WHERE нөхцлөөр мөр авах (getRowWhere)
 *
 * зэрэг үндсэн CRUD үйлдлүүдийг бүрэн хэрэгжүүлдэг.
 *
 * Бүх нэг хэлт (non-localized) хүснэгтийн моделиуд энэ классыг удамшуулна.
 *
 * @package codesaur\DataObject
 */
abstract class Model
{
    use TableTrait;

    /**
     * Өгөгдөл нэмэх (INSERT).
     *
     * MySQL/SQLite -> lastInsertId() ашиглана
     * PostgreSQL -> RETURNING * ашиглана
     *
     * @param array $record Нэмэх мөрийн түлхүүр -> утга хослол
     * @return array Амжилттай бол шинэ мөрийн бүрэн мэдээлэл (бүх багана агуулсан массив)
     * @throws Exception
     */
    public function insert(array $record): array
    {
        $driver = $this->getDriverName();

        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }

        $columns = \implode(', ', $column);
        $params = \implode(', ', $param);

        $table = $this->getName();
        $query = "INSERT INTO $table($columns) VALUES($params)";

        // PostgreSQL -> RETURNING *
        if ($driver == Constants::DRIVER_PGSQL) {
            $query .= ' RETURNING *';
        }

        $insert = $this->prepare($query);
        // Bind values
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$insert->execute()) {
            $this->throwPdoError(__CLASS__ . ": INSERT failed on [$table]! ", $insert);
        }

        // PostgreSQL -> шинэ мөрийг буцаана
        if ($driver == Constants::DRIVER_PGSQL) {
            return $insert->fetch(\PDO::FETCH_ASSOC);
        }

        // MySQL/SQLite -> ID багана байгаа бол retrive хийнэ
        $col_id = Constants::COL_ID;
        if ($this->hasColumn($col_id) && $this->getColumn($col_id)->isPrimary()) {
            // SQLite дээр lastInsertId() нь sequence name шаардлагагүй
            $sequenceName = ($driver == Constants::DRIVER_SQLITE) ? null : $col_id;
            $id = (int)($record[$col_id] ?? $this->pdo->lastInsertId($sequenceName));
            $row = $this->getRowWhere([$col_id => $id]);
            if ($row === null) {
                throw new \Exception(__CLASS__ . ": INSERT succeeded on [$table] but failed to retrieve the new row!");
            }
            return $row;
        }

        // ID байхгүй хүснэгтүүдийн хувьд нэмж өгөгдлийг автоматаар угсарна
        $row = [];
        foreach ($this->getColumns() as $column) {
            $colName = $column->getName();
            $row[$colName] = $record[$colName] ?? $column->getDefault();
        }

        return $row;
    }

    /**
     * ID-р мөр шинэчлэх (UPDATE).
     *
     * UPDATE table SET field=:value WHERE id=X
     *
     * PostgreSQL -> RETURNING *
     * MySQL/SQLite -> SELECT * WHERE id=...
     *
     * @param int $id Шинэчлэх ID
     * @param array $record Шинэчлэх талбарууд ['column' => value, ...]
     * @return array Шинэчилсэн мөрийн бүрэн мэдээлэл (бүх багана агуулсан массив)
     * @throws Exception
     */
    public function updateById(int $id, array $record): array
    {
        $table = $this->getName();

        // ID багана заавал байх
        $col_id = Constants::COL_ID;
        if (!$this->hasColumn($col_id)
            || !$this->getColumn($col_id)->isInt()
            || !$this->getColumn($col_id)->isPrimary()
        ) {
            throw new \Exception("(updateById): Table [$table] must have primary auto increment id column!");
        }

        if (empty($record)) {
            throw new \Exception("(updateById): Must provide updated record!");
        }

        $driver = $this->getDriverName();

        // UPDATE SET синтакс бэлтгэх
        $set = [];
        foreach (\array_keys($record) as $name) {
            $set[] = "$name=:$name";
        }
        $sets = \implode(', ', $set);
        $query = "UPDATE $table SET $sets WHERE $col_id=$id";
        // PostgreSQL -> RETURNING *
        if ($driver == Constants::DRIVER_PGSQL) {
            $query .= ' RETURNING *';
        }
        $update = $this->prepare($query);
        // Bind values
        foreach ($record as $name => $value) {
            $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$update->execute()) {
            $this->throwPdoError(__CLASS__ . ": UPDATE failed on [$table] for id=$id! ", $update);
        }

        // PostgreSQL -> Бүрэн мөрийг буцаах
        if ($driver == Constants::DRIVER_PGSQL) {
            return $update->fetch(\PDO::FETCH_ASSOC);
        }

        // MySQL/SQLite -> Шинэчилсэн мөрийг сонгон бүрнээр буцаах
        $row = $this->getRowWhere([$col_id => $record[$col_id] ?? $id]);
        if ($row === null) {
            throw new \Exception(__CLASS__ . ": UPDATE succeeded on [$table] for id=$id but failed to retrieve the updated row!");
        }
        return $row;
    }

    /**
     * Нөхцөлд тохирох мөрийн тоог буцаах.
     *
     * @param array $condition WHERE, JOIN гэх мэт нөхцөл (LIMIT, OFFSET, ORDER BY хэрэггүй)
     * @return int
     */
    public function countRows(array $condition = []): int
    {
        $stmt = $this->selectStatement($this->getName(), 'COUNT(*) as cnt', $condition);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Олон мөрийг авах.
     *
     * @param array $condition SELECTStatement-д өгөх нөхцөл (JOIN, WHERE, ORDER, LIMIT...)
     * @return array
     */
    public function getRows(array $condition = []): array
    {
        $col_id = Constants::COL_ID;
        $havePrimaryId =
            $this->hasColumn($col_id) &&
            $this->getColumn($col_id)->isPrimary();

        $rows = [];
        $stmt = $this->selectStatement($this->getName(), '*', $condition);

        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($havePrimaryId) {
                $rows[$data[$col_id]] = $data;
            } else {
                $rows[] = $data;
            }
        }

        return $rows;
    }

    /**
     * Нэг мөрийг авах.
     *
     * @param array $condition SELECT нөхцөл
     * @return array|null
     */
    public function getRow(array $condition = []): array|null
    {
        $stmt = $this->selectStatement($this->getName(), '*', $condition);

        // SQLite дээр rowCount() SELECT-д ажиллахгүй, fetch() ашиглана
        if ($this->getDriverName() == Constants::DRIVER_SQLITE) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row !== false) {
                // Зөвхөн нэг мөр байгаа эсэхийг шалгах (хоёр дахь мөр байвал null буцаана)
                $secondRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($secondRow === false) {
                    return $row;
                }
            }
        } elseif ($stmt->rowCount() == 1) {
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return null;
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
     * ID-р мөр авах.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): array|null
    {
        return $this->getRowWhere([Constants::COL_ID => $id]);
    }

    /**
     * WHERE key=:value хэлбэрийн синтаксаар мөр авах.
     *
     * @param array $with_values key => value массив
     * @return array|null
     */
    public function getRowWhere(array $with_values): array|null
    {
        $where = [];
        $params = [];

        foreach ($with_values as $key => $value) {
            $where[] = "$key=:$key";
            $params[":$key"] = $value;
        }

        $clause = \implode(' AND ', $where);

        return $this->getRow([
            'WHERE' => $clause,
            'PARAM' => $params,
            'LIMIT' => 1
        ]);
    }
}
