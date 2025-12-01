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
     * MySQL → lastInsertId() ашиглана  
     * PostgreSQL → RETURNING * ашиглана
     *
     * @param array $record Нэмэх мөрийн түлхүүр → утга хослол
     * @return array|false Амжилттай бол шинэ мөрийн мэдээлэл, алдаа бол false
     */
    public function insert(array $record): array|false
    {
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }

        $columns = \implode(', ', $column);
        $params = \implode(', ', $param);

        $table = $this->getName();
        $query = "INSERT INTO $table($columns) VALUES($params)";

        // PostgreSQL → RETURNING *
        if ($this->getDriverName() == 'pgsql') {
            $query .= ' RETURNING *';
        }

        $insert = $this->prepare($query);
        // Bind values
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$insert->execute()) {
            return false;
        }

        // PostgreSQL → шинэ мөрийг буцаана
        if ($this->getDriverName() == 'pgsql') {
            return $insert->fetch(\PDO::FETCH_ASSOC);
        }

        // MySQL → ID багана байгаа бол retrive хийнэ
        if ($this->hasColumn('id') && $this->getColumn('id')->isPrimary()) {
            $id = (int)($record['id'] ?? $this->pdo->lastInsertId('id'));
            return $this->getRowWhere(['id' => $id]) ?? false;
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
     * PostgreSQL → RETURNING *
     * MySQL → SELECT * WHERE id=...
     *
     * @param int $id Шинэчлэх ID
     * @param array $record Шинэчлэх талбарууд
     * @return array|false Шинэчилсэн мөрийн data, алдаа бол false
     * @throws Exception
     */
    public function updateById(int $id, array $record): array|false
    {
        $table = $this->getName();

        // ID багана заавал байх
        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception("(updateById): Table [$table] must have primary auto increment id column!");
        }

        if (empty($record)) {
            throw new \Exception("(updateById): Must provide updated record!");
        }

        // UPDATE SET синтакс бэлтгэх
        $set = [];
        foreach (\array_keys($record) as $name) {
            $set[] = "$name=:$name";
        }
        $sets = \implode(', ', $set);

        $query = "UPDATE $table SET $sets WHERE id=$id";

        // PostgreSQL → RETURNING *
        if ($this->getDriverName() == 'pgsql') {
            $query .= ' RETURNING *';
        }
        
        $update = $this->prepare($query);
        // Bind values
        foreach ($record as $name => $value) {
            $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$update->execute()) {
            return false;
        }

        // PostgreSQL → Бүрэн мөрийг буцаах
        if ($this->getDriverName() == 'pgsql') {
            return $update->fetch(\PDO::FETCH_ASSOC);
        }

        // MySQL → Шинэчилсэн мөрийг сонгох
        return $this->getRowWhere(['id' => $record['id'] ?? $id]) ?? false;
    }

    /**
     * Олон мөрийг авах.
     *
     * @param array $condition SELECTStatement-д өгөх нөхцөл (JOIN, WHERE, ORDER, LIMIT…)
     * @return array
     */
    public function getRows(array $condition = []): array
    {
        $havePrimaryId =
            $this->hasColumn('id') &&
            $this->getColumn('id')->isPrimary();

        $rows = [];
        $stmt = $this->selectStatement($this->getName(), '*', $condition);

        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($havePrimaryId) {
                $rows[$data['id']] = $data;
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

        if ($stmt->rowCount() == 1) {
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return null;
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
