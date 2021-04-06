<?php

namespace codesaur\DataObject;

use Exception;
use PDO;
use PDOStatement;

class Model
{
    use TableTrait;
    
    protected $pdo;    // PHP Data Object
    
    private $_name;    // Table name
    private $_columns; // Table columns

    function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function setTable(string $name, $collate = null): bool
    {
        $this->_name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        if (!$this->create($this->_name, $this->_columns, $collate)) {
            return false;
        }

        $this->__initial();
        
        return true;
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
        
        $fields = $values = array();
        foreach (array_keys($record) as $name) {
            if (!$this->hasColumn($name)) {
                unset($record[$name]);
                continue;
            }
            
            $fields[] = $name;
            $values[] = ":$name";
        }
        
        $insert = 'INSERT INTO ' . $this->getName();
        $insert .= ' (' . implode(', ', $fields) . ')';
        $insert .= ' VALUES (' . implode(', ', $values) . ')';
        
        $stmt = $this->prepare($insert);        
        foreach ($record as $key => $value) {
            $stmt->bindValue(":$key", $value, $this->getColumn($key)->getDataType());
        }
        
        if ($stmt->execute()) {
            $idColumn = $this->getIdColumn();            
            if (isset($record[$idColumn->getName()])) {
                return $record[$idColumn->getName()];
            }

            if ($idColumn->isIntType()) {
                return (int)$this->lastInsertId();
            }
            
            return $this->lastInsertId();
        }
        
        return false;
    }
    
    public function update(array $record, array $condition)
    {
        if (empty($condition['WHERE']) && empty($condition['LIMIT'])) {
            throw new Exception(__CLASS__ . ': Can\'t update data without proper selection!');
        }
        
        $sets = array();
        foreach (array_keys($record) as $name) {
            if (!$this->hasColumn($name)) {
                unset($record[$name]);
                continue;
            }
            
            $sets[] = "$name=:$name";
        }
        if (empty($record)) {
            throw new Exception(__CLASS__ . ': Can\'t update record with no data provided!');
        }
        
        if ($this->hasColumn('updated_at')
                && !isset($record['updated_at'])
        ) {
            $record['updated_at'] = date('Y-m-d H:i:s');
            $sets[] = 'updated_at=:updated_at';
        }
        
        if ($this->hasColumn('updated_by')
                && !isset($record['updated_by'])
                && getenv('CODESAUR_ACCOUNT_ID', true)
        ) {
            $record['updated_by'] = getenv('CODESAUR_ACCOUNT_ID', true);
            $sets[] = 'updated_by=:updated_by';
        }
        
        $updatedIds = array();
        $idColumn = $this->getIdColumn();
        $idName = $idColumn->getName();
        $update = 'UPDATE ' . $this->getName();
        $update .= ' SET ' . implode(', ', $sets);
        $update .= " WHERE $idName=:old_$idName";
        $updatestmt = $this->prepare($update);        
        $selectstmt = $this->select($idName, $condition);
        while ($row = $selectstmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row[$idName])) {
                continue;
            }
            
            $updatestmt->bindValue(":old_$idName", $row[$idName], $idColumn->getDataType());
            
            foreach ($record as $name => $value) {
                $updatestmt->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
            }

            if ($updatestmt->execute()) {
                $id = $idColumn->isIntType() ? (int)$row[$idName] : $row[$idName];
                $updatedIds[$id] = $record[$idName] ?? $id;
            }
        }
        
        return empty($updatedIds) ? false : $updatedIds;
    }
    
    public function updateById($id, array $record)
    {
        $idName = $this->getIdColumn()->getName();
        $condition = array(
            'WHERE' => "$idName=:$idName",
            'VALUES' => array($idName => $id)
        );
        
        return $this->update($record, $condition);
    }
    
    public function select(string $selection = '*', array $condition = []): PDOStatement
    {
        $select = "SELECT $selection FROM " . $this->getName();
        if (isset($condition['WHERE'])) {
            $select .= ' WHERE ' . $condition['WHERE'];
        }
        if (isset($condition['ORDER BY'])) {
            $select .= ' ORDER BY ' . $condition['ORDER BY'];
        }
        if (isset($condition['LIMIT'])) {
            $select .= ' LIMIT ' . $condition['LIMIT'];
        }
        
        $stmt = $this->prepare($select);
        if (!empty($condition['VALUES'])) {
            foreach ($condition['VALUES'] as $key => $value) {
                $data_type = $this->hasColumn($key) ?
                        $this->getColumn($key)->getDataType() : PDO::PARAM_STR;
                $stmt->bindValue(":$key", $value, $data_type);
            }
        }
        $stmt->execute();

        return $stmt;
    }
    
    public function getRows(array $condition = []): array
    {
        $idName = $this->getIdColumn()->getName();
        
        if (empty($condition)) {
            $condition['ORDER BY'] =  $idName;
            
            if ($this->hasColumn('is_active')
                    && $this->getColumn('is_active')->isIntType()
            ) {
                $condition['WHERE'] =  'is_active=1';
            }
        }
        
        $rows = array();
        $stmt = $this->select('*', $condition);
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($this->getColumns() as $column) {
                $rows[$data[$idName]][$column->getName()] = $data[$column->getName()] ?? $column->getDefault();
            }
        }
        
        return $rows;
    }
    
    public function getRowBy(array $values)
    {
        $wheres = array();
        foreach (array_keys($values) as $key) {
            $wheres[] = "$key=:$key";
        }
        
        if (!empty($wheres)) {
            $stmt = $this->select('*', array(
                'WHERE' => implode(' AND ', $wheres),
                'VALUES' => $values
            ));
            if ($stmt->rowCount() === 1) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        return null;
    }
    
    public function getById($id)
    {
        return $this->getRowBy(array($this->getIdColumn()->getName() => $id));
    }
}
