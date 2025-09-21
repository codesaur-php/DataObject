<?php

namespace codesaur\DataObject;

abstract class Model
{
    use TableTrait;
    
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
        if ($this->getDriverName() == 'pgsql') {
            $query .= ' RETURNING *';
        }
        $insert = $this->prepare($query);
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$insert->execute()) {
            return false;
        }
        
        if ($this->getDriverName() == 'pgsql') {
            return $insert->fetch(\PDO::FETCH_ASSOC);
        }
        
        if ($this->hasColumn('id') && $this->getColumn('id')->isPrimary()) {
            $id = (int) ($record['id'] ?? $this->pdo->lastInsertId('id'));
            return $this->getRowBy(['id' => $id]);
        }
        
        $row = [];
        foreach ($this->getColumns() as $column) {
            if (isset($record[$column->getName()])) {
                $row[$column->getName()] = $record[$column->getName()];
            } else {
                $row[$column->getName()] = $column->getDefault();
            }
        }
        return $row;
    }
    
    public function updateById(int $id, array $record): array|false
    {
        $table = $this->getName();
        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception("(updateById): Table [$table] must have primary auto increment id column!");
        } elseif (empty($record)) {
            throw new \Exception("(updateById): Must provide updated record!");
        }
        
        $set = [];
        foreach (\array_keys($record) as $name) {
            $set[] = "$name=:$name";
        }
        $sets = \implode(', ', $set);
        
        $query = "UPDATE $table SET $sets WHERE id=$id";
        if ($this->getDriverName() == 'pgsql') {
            $query .= ' RETURNING *';
        }
        $update = $this->prepare($query);
        foreach ($record as $name => $value) {
            $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$update->execute()) {
            return false;
        }
        
        if ($this->getDriverName() == 'pgsql') {
            return $update->fetch(\PDO::FETCH_ASSOC);
        } else {
            return $this->getRowBy(['id' => $record['id'] ?? $id]);
        }
    }
    
    public function getRows(array $condition = []): array
    {
        $havePrimaryId = $this->hasColumn('id')
            && $this->getColumn('id')->isPrimary();
        
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
    
    public function getRow(array $condition = []): array|false
    {
        $stmt = $this->selectStatement($this->getName(), '*', $condition);
        if ($stmt->rowCount() == 1) {
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        return false;
    }
    
    public function getRowBy(array $with_values): array|false
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
