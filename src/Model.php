<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

use Exception;

class Model extends Table
{
    public function setTable(string $name, $collate = null)
    {
        $this->name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        $table = $this->getName();
        $columns = $this->getColumns();
        if (empty($columns)) {
            throw new Exception(__CLASS__ . ": Must define columns before table [$table] set!");
        }
        
        if ($this->hasTable($table)) {
            return;
        }
        
        $this->create($table, $columns, $collate);
        
        $this->__initial();
    }
    
    public function insert(array $record)
    {
        if ($this->hasColumn('created_at')
                && !isset($record['created_at'])
        ) {
            $record['created_at'] = date('Y-m-d H:i:s');
        }

        if ($this->hasColumn('created_by')
                && !isset($record['created_by'])
                && getenv('CODESAUR_ACCOUNT_ID', true)
        ) {
            $record['created_by'] = getenv('CODESAUR_ACCOUNT_ID', true);
        }
        
        $table = $this->getName();
        $column = $param = array();
        foreach (array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = implode(', ', $column);
        $params = implode(', ', $param);
        
        $insert = $this->prepare("INSERT INTO $table($columns) VALUES($params)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        
        if (!$insert->execute()) {
            return false;
        }
        
        $idColumn = $this->getIdColumn();
        $insertId = $record[$idColumn->getName()] ?? $this->lastInsertId();
        
        return $idColumn->isInt() ? (int)$insertId : $insertId;
    }
    
    public function update(array $record, array $condition)
    {
        if ($this->hasColumn('updated_at')
                && !isset($record['updated_at'])
        ) {
            $record['updated_at'] = date('Y-m-d H:i:s');
        }
        
        if ($this->hasColumn('updated_by')
                && !isset($record['updated_by'])
                && getenv('CODESAUR_ACCOUNT_ID', true)
        ) {
            $record['updated_by'] = getenv('CODESAUR_ACCOUNT_ID', true);
        }
        
        $set = array();
        foreach (array_keys($record) as $name) {
            $set[] = "$name=:$name";
        }
        $sets = implode(', ', $set);
        
        $table = $this->getName();
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        
        $ids = array();
        $select = $this->select($idColumnName, $condition);
        $update = $this->prepare("UPDATE $table SET $sets WHERE $idColumnName=:old_$idColumnName");
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $update->bindValue(":old_$idColumnName", $row[$idColumnName]);
            
            foreach ($record as $name => $value) {
                $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
            }

            if ($update->execute()) {
                $oldId = $row[$idColumnName];
                $newId = $record[$idColumnName] ?? $oldId;
                $ids[$idColumn->isInt() ? (int)$oldId : $oldId] = $idColumn->isInt() ? (int)$newId : $newId;
            }
        }
        
        return empty($ids) ? false : $ids;
    }
    
    public function updateById($id, array $record)
    {
        $idColumnName = $this->getIdColumn()->getName();
        $condition = array(
            'WHERE' => "$idColumnName=:id",
            'PARAM' => array(':id' => $id)
        );
        
        return $this->update($record, $condition);
    }
    
    public function select(string $selection = '*', array $condition = []): PDOStatement
    {
        return $this->selectStatement($this->getName(), $selection, $condition);
    }
    
    public function getRows(array $condition = []): array
    {
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        
        if (empty($condition)) {
            $condition['ORDER BY'] = $idColumnName;
            
            if ($this->hasColumn('is_active')
                    && $this->getColumn('is_active')->isInt()
            ) {
                $condition['WHERE'] = 'is_active=1';
            }
        }
        
        $rows = array();
        $stmt = $this->select('*', $condition);
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $idColumn->isInt() ? (int)$data[$idColumnName] : $data[$idColumnName];
            foreach ($this->getColumns() as $column) {
                if (isset($data[$column->getName()])) {
                    if ($column->isInt()) {
                        $value = (int)$data[$column->getName()];
                    } elseif ($column->getType() == 'decimal') {
                        $value = (float)$data[$column->getName()];
                    } else {
                        $value = $data[$column->getName()];
                    }
                } else {
                    $value = $column->getDefault();
                }
                
                $rows[$id][$column->getName()] = $value;
            }
        }
        
        return $rows;
    }
    
    public function getRowBy(array $values, $orderBy = null)
    {
        $where = array();
        $params = array();
        foreach ($values as $key => $value) {
            $where[] = "$key=:$key";
            $params[":$key"] = $value;
        }
        $clause = implode(' AND ', $where);
        
        if (!empty($clause)) {
            $condition = array(
                'WHERE' => $clause,
                'LIMIT' => 1,
                'PARAM' => $params
            );
            if (!empty($orderBy)) {
                $condition['ORDER BY'] = $orderBy;
            }
            $stmt = $this->select('*', $condition);
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                foreach ($this->getColumns() as $column) {
                    if (isset($row[$column->getName()])) {
                        if ($column->isInt()) {
                            $row[$column->getName()] = (int)$row[$column->getName()];
                        } elseif ($column->getType() == 'decimal') {
                            $row[$column->getName()] = (float)$row[$column->getName()];
                        }
                    }
                }
                
                return $row;
            }
        }
        
        return null;
    }
    
    public function getById($id)
    {
        return $this->getRowBy(array($this->getIdColumn()->getName() => $id));
    }
}
