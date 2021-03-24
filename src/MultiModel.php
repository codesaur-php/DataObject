<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

class MultiModel extends Model
{
    public $content; // Content table
    
    function __construct(PDO $conn)
    {
        parent::__construct($conn);
        
        $this->content = new Table($conn);
    }
    
    final public function getKeyColumn(): Column
    {
        return $this->content->getColumn('parent_id');
    }    

    final public function getCodeColumn(): Column
    {
        return $this->content->getColumn('code');
    }    

    public function setName(string $name)
    {
        parent::setName($name);
        
        $this->content->setName($this->getName() . '_content');
    }

    public function setCreateTable(?string $name = null, $collate = null): bool
    {
        if (!empty($name)) {
            $this->setName($name);
        }

        $this->content->setCreateTable(null, $collate);
        
        return parent::setCreateTable(null, $collate);
    }

    final public function getMainColumns(): array
    {
        return $this->getColumns();
    }

    final public function setMainColumns(array $columns)
    {
        parent::setColumns($columns);
    }

    final public function getContentColumns(): array
    {
        return $this->content->getColumns();
    }

    final public function setContentColumns(array $columns)
    {
        $this->content->setColumns($columns);
        
        if (!isset($this->content->columns['parent_id'])) {
            $this->content->columns['parent_id'] = (new Column('parent_id', 'bigint', 20))->notNull();
        }

        if (!isset($this->content->columns['code'])) {
            $this->content->columns['code'] = new Column('code', 'varchar', 6);
        }
    }

    public function insertContent(array $record, array $content)
    {
        $id = parent::insert($record);
        if ($id !== false) {
            $keyName = $this->getKeyColumn()->getName();
            $codeName = $this->getCodeColumn()->getName();
            
            foreach ($content as $code => $value) {
                $value[$keyName] = $id;
                $value[$codeName] = $code;
                if ($this->content->insert($value) === false) {
                    // Should we roll back insertion from main table & in content codes? since it's failed
                }
            }
        }
        
        return $id;
    }
    
    public function updateContent(array $record, array $content, array $where_main = [], string $condition = '', array $where_content = [])
    {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();
        
        $id = parent::update($record, $where_main, $condition);
        if ($id !== false) {
            if (empty($where_content)) {
                $where_content = array($keyName, $codeName);
            }
            
            foreach ($content as $code => $value) {
                $value[$keyName] = $record[$idName];
                $value[$codeName] = $code;
                if ($this->insert_or_update_content($value, $where_content) === false) {
                    // Should we roll back update from main table & in content codes? since it's failed
                }
            }
        }
        
        return $id;
    }
    
    public function deleteContent(array $by_record, array $content_codes)
    {
        $idColumn = $this->getIdColumn();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();
        
        if (getenv('CODESAUR_DB_KEEP_DATA', true) == 'true'
                && $this->hasColumn('is_active')
        ) {
            $result = parent::delete($by_record);
            if ($result) {
                if ($idColumn->isNumeric()) {
                    $old_id = $result[$idColumn->getName()];
                } else {
                    $old_id = $this->quote(substr($result[$idColumn->getName()], strlen(uniqid()) + 3));                    
                }

                foreach ($content_codes as $code) {
                    $record = [$keyName => $result[$idColumn->getName()]];
                    $condition = "$keyName=$old_id AND ";
                    $condition .= "$codeName=" . $this->quote($code);
                    $this->content->update($record, array(), $condition);
                }
                
                return true;
            }
        } else {
            $pdostmt = $this->select('*', $by_record);
            $row = $pdostmt->fetch(PDO::FETCH_ASSOC);
            foreach ($content_codes as $code) {
                $this->content->delete(array($keyName => $row[$idColumn->getName()], $codeName => $code));
            }
            
            return parent::delete($by_record);
        }
        
        return false;
    }

    public function getByID($value, $language_code = null): array
    {
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        if (!$idColumn->isNumericType()) {
            $value = $this->quote($value);
        }
        
        $c_codeName = "c_$codeName";
        $c_idName = $this->content->getIdColumn()->getName();
        $content_KeyColumns = array($c_idName, $keyName, $codeName);

        $clause = "p.$idColumnName=$value";
        if (isset($language_code)) {
           $clause .= " AND c.$codeName=" . $this->quote($language_code);
        }        
        $pdostmt = $this->join_select('*', ['WHERE' => $clause]);

        $record = array();
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($record[$idColumnName])) {
                foreach ($this->getMainColumns() as $column) {
                    $columnName = $column->getName();
                    $record[$columnName] = $data["p_$columnName"] ?? $column->getDefault();
                }
            }
            
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();                
                if (!in_array($ccolumnName, $content_KeyColumns)) {
                    $record['content'][$ccolumnName][$data[$c_codeName]] = $data["c_$ccolumnName"] ?? $ccolumn->getDefault();
                }
            }
        }
        
        return $record;
    }    
    
    public function getRow(array $by_record)
    {
        $idName = $this->getIdColumn()->getName();
        
        $row = parent::getRow($by_record);        
        if (isset($row[$idName])) {
            return $this->getByID($row[$idName]);
        }
        
        return null;
    }

    public function getRows(array $condition = []): array
    {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        if (empty($condition)) {
            $condition = ['ORDER BY' => "p.$idName"];
        }

        $p_idName = "p_$idName";
        $c_codeName = "c_$codeName";
        $c_idName = $this->content->getIdColumn()->getName();
        $content_KeyColumns = array($c_idName, $keyName, $codeName);
        
        $rows = array();
        $pdostmt = $this->join_select();
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($rows[$data[$p_idName]][$p_idName])) {
                foreach ($this->getMainColumns() as $column) {
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

    public function duplicateContentByCode(string $source_code, string $destination_code)
    {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        $contentColumns = $this->getContentColumns();
        $selections = array("p.$idName as p_$idName");
        foreach ($contentColumns as $key => $column) {
            if ($key != $idName) {
                $selections[] = "c.$key as c_$key";
            } else {
                unset($contentColumns[$key]);
            }
        }
        
        $pdostmt = $this->join_select(implode(',', $selections),
                array('WHERE' => "c.$codeName=" . $this->quote($source_code)));
        $pdostmt->execute();
        
        if ($pdostmt->rowCount() > 0) {
            unset($contentColumns[$keyName]);
            unset($contentColumns[$codeName]);
            
            $content = array();
            while ($row = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
                foreach ($contentColumns as $column) {
                    $content[$column] = $row[$column] ?? '';
                }
                $content[$keyName] = $row[$keyName];
                $content[$codeName] = $destination_code;
                
                $this->insert_or_update_content($content, array($keyName, $codeName));
            }
        }
    }
    
    private function insert_or_update_content(array $content, array $where)
    {
        $by_record = array();
        foreach ($where as $name) {
            if ($this->content->hasColumn($name)) {
                $by_record[$name] = $content[$name];
            }
        }
        
        $stmt = $this->content->select('*', $by_record);
        if ($stmt->rowCount()) {
            return $this->content->update($content, $where);
        } else {
            return $this->content->insert($content);
        }
        
        return false;
    }
    
    private function join_select($selection = '*', array $condition = []): PDOStatement
    {
        $contentName = $this->content->getName();
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();

        if ($selection === '*') {
            $selections = array();
            foreach (array_keys($this->getMainColumns()) as $column) {
                $selections[] = "p.$column as p_$column";
            }
            
            foreach (array_keys($this->getContentColumns()) as $column) {
                $selections[] = "c.$column as c_$column";
            }
            
            $selection = implode(',', $selections);
        }
        
        $condition['JOIN'] = "p INNER JOIN $contentName c ON p.$idName=c.$keyName";
        
        return parent::select($selection, [], $condition);
    }
}
