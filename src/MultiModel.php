<?php

namespace codesaur\DataObject;

use Exception;
use PDO;
use PDOStatement;

class MultiModel
{
    use TableTrait;
    
    protected $pdo;            // PHP Data Object
    
    private $_name;            // Record table name
    private $_columns;         // Record table columns
    private $_contentColumns;  // Content table columns

    function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function setTable(string $name, $collate = null): bool
    {
        $this->_name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        $newly_created = $this->create($this->_name, $this->_columns, $collate);
        
        $contentColumns = $this->getContentColumns();
        if (!isset($contentColumns['parent_id'])) {
            throw new Exception(__CLASS__ . ': Content table columns is not properly set!');
        }
        $contentColumns['parent_id']->foreignKey($this->_name . '(' . $this->getIdColumn()->getName() . ') ON DELETE CASCADE ON UPDATE CASCADE');
        $this->create($this->getContentName(), $contentColumns, $collate);
        
        if ($newly_created) {
            $this->__initial();
        }
        
        return $newly_created;
    }

    public function getContentName(): string
    {
        return $this->getName() . '_content';
    }

    public function getContentColumns(): array
    {
        return $this->_contentColumns ?? array();
    }

    public function getContentColumn(string $name): ?Column
    {
        return $this->_contentColumns[$name] ?? null;
    }

    public function getKeyColumn(): Column
    {
        if (isset($this->_contentColumns['parent_id'])) {
            return $this->_contentColumns['parent_id'];
        }
        
        throw new Exception(__CLASS__ . ": Table [{$this->getName()}] definition doesn't have content key column!");
    }

    public function getCodeColumn(): Column
    {
        if (isset($this->_contentColumns['code'])) {
            return $this->_contentColumns['code'];
        }
        
        throw new Exception(__CLASS__ . ": Table [{$this->getName()}] definition doesn't have content code column!");
    }

    public function setContentColumns(array $columns)
    {
        $idColumn = $this->getIdColumn();
        if (!$idColumn instanceof Column) {
            throw new Exception(__CLASS__ . ': Must define record id column before to set content columns!');
        }
        
        $contentColumns = array();
        if (!isset($columns['id'])) {
            $contentColumns['id'] = (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull();
        }
        
        $parent_id = clone $idColumn;
        $parent_id->primary(false)->auto(false)->unique(false)->setName('parent_id');
        if (isset($columns[$parent_id->getName()])) {
            unset($columns[$parent_id->getName()]);
        }        
        $contentColumns[$parent_id->getName()] = $parent_id;
        
        $code = new Column('code', 'varchar', 6);
        if (isset($columns[$code->getName()])) {
            unset($columns[$code->getName()]);
        }
        $contentColumns[$code->getName()] = $code;

        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new Exception(__CLASS__ . ': Column should have been instance of Column class!');
            } elseif ($column->isUnique()) {
                throw new Exception(__CLASS__ . ": Content table forbidden to contain unique column [{$column->getName()}]!");
            }
            
            $contentColumns[$column->getName()] = $column;
        }

        $this->_contentColumns = $contentColumns;
    }

    public function insert(array $record, array $content)
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
        if ($stmt->execute() === false) {
            return false;
        }
        
        $idColumn = $this->getIdColumn();            
        $id = $record[$idColumn->getName()] ?? ($idColumn->isIntType() ? (int)$this->lastInsertId() : $this->lastInsertId());
        
        $contentTable = $this->getContentName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();
        foreach ($content as $code => $data) {
            $content_fields = $content_values = array();
            foreach (array_keys($data) as $name) {
                if (!$this->getContentColumn($name) instanceof Column) {
                    unset($data[$name]);
                    continue;
                }

                $content_fields[] = $name;
                $content_values[] = ":$name";
            }
            $data[$keyName] = $id;
            $data[$codeName] = $code;
            $content_fields[] = $keyName;
            $content_values[] = ":$keyName";
            $content_fields[] = $codeName;
            $content_values[] = ":$codeName";

            $content_insert = "INSERT INTO $contentTable";
            $content_insert .= ' (' . implode(', ', $content_fields) . ')';
            $content_insert .= ' VALUES (' . implode(', ', $content_values) . ')';
            
            $content_stmt = $this->prepare($content_insert);
            foreach ($data as $key => $value) {
                $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
            }
            if ($content_stmt->execute() === false) {
                // Should we roll back insertion from main table & in content codes? since it's failed
            }
        }
        
        return $id;
    }
    
    public function update(array $record, array $content, array $condition)
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
        if (empty($record) && empty($content)) {
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

        $idColumn = $this->getIdColumn();
        $idName = $idColumn->getName();        
        $select = "SELECT p.$idName as $idName FROM {$this->getName()} p";
        if (isset($condition['INNER JOIN'])) {
            $select .= ' INNER JOIN ' . $condition['INNER JOIN'];
        }
        if (isset($condition['WHERE'])) {
            $select .= ' WHERE ' . $condition['WHERE'];
        }
        if (isset($condition['ORDER BY'])) {
            $select .= ' ORDER BY ' . $condition['ORDER BY'];
        }
        if (isset($condition['LIMIT'])) {
            $select .= ' LIMIT ' . $condition['LIMIT'];
        }
        $selectstmt = $this->prepare($select);
        if (!empty($condition['VALUES'])) {
            foreach ($condition['VALUES'] as $key => $value) {
                $data_type = $this->hasColumn($key) ?
                        $this->getColumn($key)->getDataType() : PDO::PARAM_STR;
                $selectstmt->bindValue(":$key", $value, $data_type);
            }
        }
        $selectstmt->execute();
        
        $updatedIds = array();
        if (!empty($sets)) {
            $record_set = implode(', ', $sets);
            $record_update = $this->prepare("UPDATE {$this->getName()} SET $record_set WHERE $idName=:old_$idName");
        }
        $content_name = $this->getContentName();
        if (!empty($content)) {
            $keyColumn = $this->getKeyColumn();
            $codeColumn = $this->getCodeColumn();
            $keyName = $keyColumn->getName();
            $codeName = $codeColumn->getName();
            $content_select = $this->prepare("SELECT id FROM $content_name WHERE $keyName=:key AND $codeName=:code LIMIT 1");
        }
        while ($row = $selectstmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row[$idName])) {
                continue;
            }
            
            $p_id = $idColumn->isIntType() ? (int)$row[$idName] : $row[$idName];
            if (!isset($updatedIds[$p_id])) {
                if (isset($record_update)) {
                    $record_update->bindValue(":old_$idName", $p_id, $idColumn->getDataType());

                    foreach ($record as $name => $value) {
                        $record_update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
                    }

                    if (!$record_update->execute()) {
                        throw new Exception(__CLASS__ . ': Error while updating record!');
                    }
                }
                
                $contentIds = array();
                foreach ($content as $code => $value) {
                    foreach (array_keys($value) as $n) {
                        $col = $this->getContentColumn($n);
                        if (!$col instanceof Column
                                || $col->getName() === $keyName
                                || $col->getName() === $codeName) {
                            unset($value);
                        }
                    }                    
                    if (empty($value)) {
                        continue;
                    }
                    
                    $content_select->bindValue(':key', $p_id, $keyColumn->getDataType());
                    $content_select->bindValue(':code', $code, $codeColumn->getDataType());
                    $content_select->execute();
                    $content_row = $content_select->fetch(PDO::FETCH_ASSOC);
                    if (isset($content_row['id'])) {
                        $content_sets = array();
                        foreach (array_keys($value) as $n) {
                            $content_sets[] = "$n=:$n";
                        }
                        $content_stmt = $this->prepare("UPDATE $content_name SET " . implode(', ', $content_sets) . ' WHERE id=:id');
                        $content_stmt->bindValue(':id', $content_row['id']);                       
                    } else {
                        $content_cols = array();
                        $content_binds = array();
                        foreach (array_keys($value) as $n) {
                            $content_cols[] = $n;
                            $content_binds[] = ":$n";
                        }
                        $content_cols[] = $keyName;
                        $content_binds[] = ":$keyName";
                        $content_cols[] = $codeName;
                        $content_binds[] = ":$codeName";
                        $content_stmt = $this->prepare("INSERT INTO $content_name (" . implode(', ', $content_cols) . ') VALUES (' . implode(', ', $content_binds) . ')');
                        $content_stmt->bindValue(":$keyName", $p_id, $keyColumn->getDataType());
                        $content_stmt->bindValue(":$codeName", $code, $codeColumn->getDataType());
                    }
                    
                    foreach ($value as $key => $value) {
                        $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
                    }
                    
                    if ($content_stmt->execute() === false) {
                        // Should we roll back update from record table & in content codes? 
                    }
                    
                    $contentIds[] = $content_row['id'] ?? $this->lastInsertId();
                }
                
                $updatedIds[$p_id] = array(($record[$idName] ?? $p_id) => $contentIds);
            }
        }
        
        return empty($updatedIds) ? false : $updatedIds;
    }
    
    public function updateById($id, array $record, array $content)
    {
        $idName = $this->getIdColumn()->getName();
        $condition = array(
            'WHERE' => "p.$idName=:$idName",
            'VALUES' => array($idName => $id)
        );
        
        return $this->update($record, $content, $condition);
    }
    
    public function select(string $selection = '*', array $condition = []): PDOStatement
    {
        if ($selection === '*') {
            $selections = array();
            foreach (array_keys($this->getColumns()) as $column) {
                $selections[] = "p.$column as p_$column";
            }
            
            foreach (array_keys($this->getContentColumns()) as $column) {
                $selections[] = "c.$column as c_$column";
            }
            
            $selection = implode(',', $selections);
        }
        
        $name = $this->getName();
        $contentName = $this->getContentName();
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        
        $select = "SELECT $selection FROM $name p INNER JOIN $contentName c ON p.$idName=c.$keyName";
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
        $codeName = $this->getCodeColumn()->getName();

        if (empty($condition)) {
            $condition = ['ORDER BY' => "p.$idName"];
        }

        $p_idName = "p_$idName";
        $c_codeName = "c_$codeName";
        $content_KeyColumns = array('id', $this->getKeyColumn()->getName(), $codeName);
        
        $rows = array();
        $pdostmt = $this->select('*', $condition);
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($rows[$data[$p_idName]][$p_idName])) {
                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();
                    $rows[$data[$p_idName]][$columnName] = $data["p_$columnName"] ?? $column->getDefault();
                }
            }
            
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();
                if (!in_array($ccolumnName, $content_KeyColumns)) {
                    $rows[$data[$p_idName]]['content'][$ccolumnName][$data[$c_codeName]] = $data["c_$ccolumnName"] ?? $ccolumn->getDefault();
                }
            }
        }
        
        return $rows;
    }
    
    public function getRowBy(array $values)
    {
        $count = 1;
        $vars = array();
        $wheres = array();
        foreach ($values as $key => $value) {
            $vars[$count] = $value;
            $wheres[] = "$key=:$count";
            $count++;
        }
        
        if (!empty($wheres)) {
            $stmt = $this->select('*', array(
                'WHERE' => implode(' AND ', $wheres),
                'VALUES' => $vars
            ));
            
            $idName = $this->getIdColumn()->getName();
            $codeName = $this->getCodeColumn()->getName();
            $c_codeName = "c_$codeName";
            $content_KeyColumns = array('id', $this->getKeyColumn()->getName(), $codeName);
            
            $row = array();
            while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($row[$idName])) {
                    foreach ($this->getColumns() as $column) {
                        $columnName = $column->getName();
                        $row[$columnName] = $data["p_$columnName"] ?? $column->getDefault();
                    }
                }

                foreach ($this->getContentColumns() as $ccolumn) {
                    $ccolumnName = $ccolumn->getName();
                    if (!in_array($ccolumnName, $content_KeyColumns)) {
                        $row['content'][$ccolumnName][$data[$c_codeName]] = $data["c_$ccolumnName"] ?? $ccolumn->getDefault();
                    }
                }
            }
            return $row;
        }
        
        return null;
    }
    
    public function getById($id, $code = null)
    {
        $values = array('p.' . $this->getIdColumn()->getName() => $id);
        if (!empty($code)) {
            $values[$this->getCodeColumn()->getName()] = $code;
        }
        
        return $this->getRowBy($values);
    }
}
