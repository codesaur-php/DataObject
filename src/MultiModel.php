<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

use Exception;

class MultiModel extends Table
{
    protected $contentColumns = array(); // Content table columns

    public function setTable(string $name, $collate = null)
    {
        $this->name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        $table = $this->getName();
        $columns = $this->getColumns();
        if (empty($columns) || empty($this->contentColumns)) {
            throw new Exception(__CLASS__ . ": Must define columns before table [$table] set!");
        }
        
        if ($this->hasTable($table)) {
            return;
        }
        
        $this->create($table, $columns, $collate);

        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $this->contentColumns[$keyName]->foreignKey($table, $idName, 'CASCADE');
        $this->create($this->getContentName(), $this->contentColumns, $collate);

        $this->__initial();
    }

    public function getContentName(): string
    {
        return $this->getName() . '_content';
    }

    public function getContentColumns(): array
    {
        return $this->contentColumns;
    }

    public function getContentColumn(string $name): Column
    {
        if (isset($this->contentColumns[$name])) {
            return $this->contentColumns[$name];
        }
        
        throw new Exception(__CLASS__ . ": Table [{$this->getContentName()}] definition doesn't have content column named [$name]!");
    }

    public function getKeyColumn(): Column
    {
        return $this->getContentColumn('parent_id');
    }

    public function getCodeColumn(): Column
    {
        return $this->getContentColumn('code');
    }

    public function setContentColumns(array $columns)
    {
        $contentColumns = array('id' => (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull());
        
        $parent_id = clone $this->getIdColumn();
        $parent_id->primary(false)->auto(false)->unique(false)->setName('parent_id');
        $contentColumns[$parent_id->getName()] = $parent_id;
        
        $code = new Column('code', 'varchar', 6);        
        $contentColumns[$code->getName()] = $code;

        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new Exception(__CLASS__ . ': Column should have been instance of Column class!');
            } elseif (isset($contentColumns[$column->getName()])) {
                continue;
            } elseif ($column->isUnique()) {
                throw new Exception(__CLASS__ . ": Content table forbidden to contain unique column [{$column->getName()}]!");
            }
            
            $contentColumns[$column->getName()] = $column;
        }

        $this->contentColumns = $contentColumns;
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
        
        $table = $this->getName();
        $column = $param = array();
        foreach (array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = implode(', ', $column);
        $values = implode(', ', $param);
        
        $insert = $this->prepare("INSERT INTO $table($columns) VALUES($values)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        
        if (!$insert->execute()) {
            return false;
        }
        
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $idRaw = $record[$idColumnName] ?? $this->lastInsertId();
        $insertId = $idColumn->isInt() ? (int)$idRaw : $idRaw;
        
        $contentTable = $this->getContentName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();
        foreach ($content as $code => $data) {
            $content_field = $content_value = array();
            foreach (array_keys($data) as $key) {
                $content_field[] = $key;
                $content_value[] = ":$key";
            }
            $data[$keyName] = $insertId;
            $data[$codeName] = $code;
            $content_field[] = $keyName;
            $content_value[] = ":$keyName";
            $content_field[] = $codeName;
            $content_value[] = ":$codeName";

            $fields = implode(', ', $content_field);
            $values = implode(', ', $content_value);
            $content_stmt = $this->prepare("INSERT INTO $contentTable($fields) VALUES($values)");
            foreach ($data as $key => $value) {
                $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
            }
            
            try {
                if (!$content_stmt->execute()) {
                    throw new Exception(implode(': ', $content_stmt->errorInfo()));
                }
            } catch (Exception $e) {
                $delete = $this->prepare("DELETE FROM $table WHERE $idColumnName=:id");
                $delete->execute(array(':id' => $insertId));
                throw new Exception(__CLASS__ . ": Failed to insert content on table [$contentTable]! " . $e->getMessage());
            }
        }
        
        return $insertId;
    }
    
    public function update(array $record, array $content, array $condition)
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
        
        $table = $this->getName();
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $selection = "p.$idColumnName as $idColumnName";
        if (!empty($record)) {
            $set = array();
            foreach (array_keys($record) as $name) {
                $set[] = "$name=:$name";
                if ($name != $idColumnName) {
                    $selection .= ", p.$name as $name";
                }
            }
            $sets = implode(', ', $set);
            $update = $this->prepare("UPDATE $table SET $sets WHERE $idColumnName=:old_$idColumnName");
        }
        
        $contentTable = $this->getContentName();
        if (!empty($content)) {
            $keyColumn = $this->getKeyColumn();
            $codeColumn = $this->getCodeColumn();
            $keyName = $keyColumn->getName();
            $codeName = $codeColumn->getName();
            $content_select = $this->prepare("SELECT id FROM $contentTable WHERE $keyName=:key AND $codeName=:code LIMIT 1");
        }
        
        $ids = array();        
        $select = $this->selectStatement("$table p", $selection, $condition);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {            
            $p_id = $idColumn->isInt() ? (int)$row[$idColumnName] : $row[$idColumnName];
            if (!isset($ids[$p_id])) {
                if (isset($update)) {
                    $update->bindValue(":old_$idColumnName", $p_id, $idColumn->getDataType());

                    foreach ($record as $name => $value) {
                        $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
                    }

                    if (!$update->execute()) {
                        throw new Exception(__CLASS__ . ": Error while updating record on table [$table:$p_id]!");
                    }
                }
                
                $newId = $record[$idColumnName] ?? $p_id;
                if ($idColumn->isInt()) {
                    $newId = (int)$newId;
                }
                
                $contentIds = array();
                foreach ($content as $code => $value) {
                    foreach (array_keys($value) as $key) {
                        if ($key == $keyName || $key == $codeName) {
                            unset($value);
                        }
                    }
                    if (empty($value)) {
                        continue;
                    }
                    
                    $content_select->bindValue(':key', $newId, $keyColumn->getDataType());
                    $content_select->bindValue(':code', $code, $codeColumn->getDataType());
                    $content_select->execute();
                    $content_row = $content_select->fetch(PDO::FETCH_ASSOC);
                    if (isset($content_row['id'])) {
                        $content_set = array();
                        foreach (array_keys($value) as $n) {
                            $content_set[] = "$n=:$n";
                        }
                        $content_sets = implode(', ', $content_set);
                        $content_stmt = $this->prepare("UPDATE $contentTable SET $content_sets WHERE id=:id");
                        $content_stmt->bindValue(':id', $content_row['id']);
                    } else {
                        $content_col = array();
                        $content_bind = array();
                        foreach (array_keys($value) as $n) {
                            $content_col[] = $n;
                            $content_bind[] = ":$n";
                        }
                        $content_cols = implode(', ', $content_col) . ", $keyName, $codeName";
                        $content_binds = implode(', ', $content_bind) . ", :$keyName, :$codeName";
                        $content_stmt = $this->prepare("INSERT INTO $contentTable($content_cols) VALUES($content_binds)");
                        $content_stmt->bindValue(":$keyName", $p_id, $keyColumn->getDataType());
                        $content_stmt->bindValue(":$codeName", $code, $codeColumn->getDataType());
                    }
                    
                    foreach ($value as $key => $value) {
                        $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
                    }
                    
                    try {
                        if (!$content_stmt->execute()) {
                            throw new Exception(implode(': ', $content_stmt->errorInfo()));
                        }
                    } catch (Exception $e) {
                        if (isset($update)) {
                            $update->bindValue(":old_$idColumnName", $newId, $idColumn->getDataType());
                            foreach (array_keys($record) as $name) {
                                $update->bindValue(":$name", $row[$name], $this->getColumn($name)->getDataType());
                            }
                            $update->execute();
                        }                        
                        throw new Exception(__CLASS__ . ": Failed to update content on table [$contentTable]! " . $e->getMessage());
                    }
                    
                    $contentIds[] = (int)($content_row['id'] ?? $this->lastInsertId());
                }
                
                $ids[$p_id] = array($newId => $contentIds);
            }
        }
        
        return empty($ids) ? false : $ids;
    }
    
    public function updateById($id, array $record, array $content)
    {
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $condition = array(
            'WHERE' => "p.$idColumnName=:$idColumnName",
            'PARAM' => array(":$idColumnName" => ['value' => $id, 'data_type' => $idColumn->getDataType()])
        );
        
        return $this->update($record, $content, $condition);
    }
    
    public function select(string $selection = '*', array $condition = []): PDOStatement
    {
        if ($selection == '*') {
            $fields = array();
            foreach (array_keys($this->getColumns()) as $column) {
                $fields[] = "p.$column as p_$column";
            }
            
            foreach (array_keys($this->getContentColumns()) as $column) {
                $fields[] = "c.$column as c_$column";
            }
            
            $selection = implode(', ', $fields);
        }
        
        $table = $this->getName();
        $contentTable = $this->getContentName();
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $condition['INNER JOIN'] = "$contentTable c ON p.$idName=c.$keyName";
        
        return $this->selectStatement("$table p", $selection, $condition);
    }

    public function getRows(array $condition = []): array
    {
        $idColumn = $this->getIdColumn();        
        $idColumnName = $idColumn->getName();
        $codeName = $this->getCodeColumn()->getName();

        if (empty($condition)) {
            $condition = ['ORDER BY' => "p.$idColumnName"];
            
            if ($this->hasColumn('is_active')
                    && $this->getColumn('is_active')->isInt()
            ) {
                $condition['WHERE'] = 'p.is_active=1';
            }
        }

        $p_idName = "p_$idColumnName";
        $c_codeName = "c_$codeName";
        $content_KeyColumns = array('id', $this->getKeyColumn()->getName(), $codeName);
        
        $rows = array();
        $pdostmt = $this->select('*', $condition);
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            $p_id = $idColumn->isInt() ? (int)$data[$p_idName] : $data[$p_idName];
            if (!isset($rows[$p_id][$p_idName])) {
                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();
                    if (isset($data["p_$columnName"])) {
                        if ($column->isInt()) {
                            $value = (int)$data["p_$columnName"];
                        } elseif ($column->getType() == 'decimal') {
                            $value = (float)$data["p_$columnName"];
                        } else {
                            $value = $data["p_$columnName"];
                        }
                    } else {
                        $value = $column->getDefault();
                    }                    
                    $rows[$p_id][$columnName] = $value;
                }
            }
            
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();
                if (!in_array($ccolumnName, $content_KeyColumns)) {
                    if (isset($data["c_$ccolumnName"])) {
                        if ($ccolumn->isInt()) {
                            $value = (int)$data["c_$ccolumnName"];
                        } elseif ($ccolumn->getType() == 'decimal') {
                            $value = (float)$data["c_$ccolumnName"];
                        } else {
                            $value = $data["c_$ccolumnName"];
                        }
                    } else {
                        $value = $ccolumn->getDefault();
                    }
                    $rows[$p_id]['content'][$ccolumnName][$data[$c_codeName]] = $value;
                }
            }
        }
        
        return $rows;
    }
    
    public function getRowBy(array $values, $orderBy = null)
    {
        $count = 1;
        $params = array();
        $wheres = array();
        foreach ($values as $key => $value) {
            $params[":$count"] = $value;
            $wheres[] = "$key=:$count";
            $count++;
        }
        $clause = implode(' AND ', $wheres);
        
        if (!empty($wheres)) {
            $condition = array(
                'WHERE' => $clause,
                'PARAM' => $params
            );
            if (!empty($orderBy)) {
                $condition['ORDER BY'] = $orderBy;
            }
            $stmt = $this->select('*', $condition);
            
            $idName = $this->getIdColumn()->getName();
            $codeName = $this->getCodeColumn()->getName();
            $c_codeName = "c_$codeName";
            $content_KeyColumns = array('id', $this->getKeyColumn()->getName(), $codeName);
            
            $row = array();
            while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($row[$idName])) {
                    foreach ($this->getColumns() as $column) {
                        $columnName = $column->getName();
                        if (isset($data["p_$columnName"])) {
                            if ($column->isInt()) {
                                $value = (int)$data["p_$columnName"];
                            } elseif ($column->getType() == 'decimal') {
                                $value = (float)$data["p_$columnName"];
                            } else {
                                $value = $data["p_$columnName"];
                            }
                        } else {
                            $value = $column->getDefault();
                        }                        
                        $row[$columnName] = $value;
                    }
                }

                foreach ($this->getContentColumns() as $ccolumn) {
                    $ccolumnName = $ccolumn->getName();
                    if (!in_array($ccolumnName, $content_KeyColumns)) {
                        if (isset($data["c_$ccolumnName"])) {
                            if ($ccolumn->isInt()) {
                                $value = (int)$data["c_$ccolumnName"];
                            } elseif ($ccolumn->getType() == 'decimal') {
                                $value = (float)$data["c_$ccolumnName"];
                            } else {
                                $value = $data["c_$ccolumnName"];
                            }
                        } else {
                            $value = $ccolumn->getDefault();
                        }                        
                        $row['content'][$ccolumnName][$data[$c_codeName]] = $value;
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
            $values['c.' . $this->getCodeColumn()->getName()] = $code;
        }
        
        return $this->getRowBy($values);
    }
}
