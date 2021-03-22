<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

class MultiModel extends InitableModel
{
    public $content; // Content table
    
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->content = new Table($pdo);
    }
    
    final public function getKeyColumn(): Column
    {
        return $this->content->getColumn('key_id');
    }    

    final public function getCodeColumn(): Column
    {
        return $this->content->getColumn('code');
    }    

    public function setName(string $name)
    {
        parent::setName($name);
        
        $this->content->setName("{$name}_content");
    }

    public function setTable(?string $name = null): bool
    {
        if (!empty($name)) {
            $this->setName($name);
        }

        if ($this->content->setTable()) {
            return parent::setTable();
        }
        
        return false;
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
        $this->content->setColumns(array_merge(array(
           (new Column('key_id', 'bigint', 20))->notNull(),
            new Column('code', 'varchar', 6)
        ), $columns));
    }

    public function inserts(
            array $main,
            array $content
    ) {
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        $id = parent::insert($main);
        if ($id !== false) {
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
    
    public function updates(
            array  $main,
            array  $content,
            array  $where_main = [],
            string $condition = '',
            array  $where_content = []
    ) {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();
        
        $id = parent::update($main, $where_main, $condition);
        if ($id !== false) {
            if (empty($where_content)) {
                $where_content = array($keyName, $codeName);
            }
            
            foreach ($content as $code => $value) {
                $value[$keyName] = $main[$idName];
                $value[$codeName] = $code;
                if ($this->writeContent($value, $where_content) === false) {
                    // Should we roll back update from main table & in content codes? since it's failed
                }
            }
        }
        
        return $id;
    }

    public function replaces(
            array  $main,
            array  $content,
            string $keywordName = '_keyword_'
    ) {
        $idName = $this->getIdColumn()->getName();
        if (isset($main[$keywordName])) {
            $existing = $this->getBy($keywordName, $main[$keywordName]);
            if ($existing) {
                $main[$idName] = $existing[$idName];

                return $this->updates($main, $content);
            }
        }
        
        return $this->inserts($main, $content);
    }

    public function writeContent(
            array $content,
            array $where,
            bool  $replace = true
    ) {
        $by_record = array();
        foreach ($where as $name) {
            if ($this->content->hasColumn($name)) {
                $by_record[$name] = $content[$name];
            }
        }
        
        $stmt = $this->content->select('*', $by_record);
        if ($stmt->rowCount()) {
            if ($replace) {
                return $this->content->update($content, $where);
            }            
        } else {
            return $this->content->insert($content);
        }
        
        return false;
    }
    
    public function selectJoin($selection, array $condition = []): PDOStatement
    {
        $contentName = $this->content->getName();
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        
        $condition['JOIN'] = "p INNER JOIN $contentName c ON p.$idName=c.$keyName";
        
        return parent::select($selection, [], $condition);
    }
    
    public function statement(
            array $main = [],
            array $content = [],
            array $condition = []
    ): PDOStatement {
        if (empty($main) && empty($content)) {
            $selection = '*';
        } else {
            $selection = '';
            
            if (empty($main)) {
                $main = array_keys($this->getMainColumns());
            }
            
            if (empty($content)) {
                $content = array_keys($this->getContentColumns());
            }
            
            foreach ($main as $name) {
                if (!$this->hasColumn($name)) {
                    // should throw Exception not found main column!
                    continue;
                }
                if ($selection != '') {
                    $selection .= ', ';
                }
                $selection .= 'p.' . $name;
            }
            
            foreach ($content as $name) {
                if (!$this->content->hasColumn($name)) {
                    // should throw Exception not found content column!
                    continue;
                }
                if ($selection != '') {
                    $selection .= ', ';
                }
                $selection .= 'c.' . $name;
            }
        }
        
        return $this->selectJoin($selection, $condition);
    }

    public function statementBy($id, string $language_code =  null): PDOStatement
    {
        $idColumn = $this->getIdColumn();
        if (!$idColumn->isNumericType()) {
            $id = $this->quote($id);
        }
        
        $clause = "p.{$idColumn->getName()}=$id";
        if (isset($language_code)) {
            $clause .= ' AND c.' . $this->getCodeColumn()->getName() . '=' . $this->quote($language_code);
        }
        
        return $this->selectJoin('*', ['WHERE' => $clause]);
    }

    public function getBy(string $name, string $value)
    {
        if ($this->hasColumn($name)) {
            $column = $this->getColumn($name);

            $stmt = $this->prepare('SELECT * FROM ' . $this->getName()
                    . ' WHERE ' . $column->getName() . '=' . $column->getBindName());
            
            $stmt->bindParam($column->getBindName(), $value, $column->getDataType(),
                    !in_array($column->getType(), array('text', 'datetime')) ? $column->getLength(): null);
            
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $this->getByID($result[$this->getIdColumn()->getName()]);
            }
        }
        
        return null;
    }

    public function getByID($value, $language_code = null): array
    {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        $record = array();
        $pdostmt = $this->statementBy($value, $language_code);
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($record[$idName])) {
                foreach ($this->getMainColumns() as $column) {
                    $record[$column->getName()] = $data[$column->getName()] ?? $column->getDefault();
                }
            }
            
            foreach ($this->getContentColumns() as $ccolumn) {                
                if ($ccolumn->getName() != $idName
                        && $ccolumn->getName() != $keyName
                        && $ccolumn->getName() != $codeName
                ) {
                    $record[$ccolumn->getName()][$data[$codeName]] = $data[$ccolumn->getName()] ?? $ccolumn->getDefault();
                }
            }
        }
        
        return $record;
    }
    
    public function getRows(array $condition = []): array
    {
        if (empty($condition)) {
            $condition = ['ORDER BY' => 'p.' . $this->getIdColumn()->getName()];
        }
        
        return $this->getStatementRows($this->selectJoin('*', $condition));
    }
    
    public function getStatementRows(PDOStatement $pdostmt): array
    {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        $rows = array();
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($rows[$data[$idName]][$idName])) {
                foreach ($this->getMainColumns() as $column) {
                    $rows[$data[$idName]][$column->getName()] = $data[$column->getName()] ?? $column->getDefault();
                }
            }
            
            foreach ($this->getContentColumns() as $ccolumn) {
                if ($ccolumn->getName() != $idName
                        && $ccolumn->getName() != $keyName
                        && $ccolumn->getName() != $codeName
                ) {
                    $rows[$data[$idName]][$ccolumn->getName()][$data[$codeName]] = $data[$ccolumn->getName()] ?? $ccolumn->getDefault();
                }
            }
        }
        
        return $rows;
    }

    public function deletes(array $by_record, array $language_codes)
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

                foreach ($language_codes as $code) {
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
            foreach ($language_codes as $code) {
                $this->content->delete(array($keyName => $row[$idColumn->getName()], $codeName => $code));
            }
            
            return parent::delete($by_record);
        }
        
        return false;
    }
    
    public function duplicateCodeContent(string $source_code, string $destination_code)
    {
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();

        $content_cols = [];
        foreach ($this->getContentColumns() as $column) {
            if ($column->getName() != $idName) {
                $content_cols[] = $column->getName();
            }
        }
        
        $pdostmt = $this->statement(
                array($idName), $content_cols,
                array('WHERE' => "c.$codeName=" . $this->quote($source_code)));
        $pdostmt->execute();
        
        if ($pdostmt->rowCount() > 0) {
            unset($content_cols[$keyName]);
            unset($content_cols[$codeName]);
            
            $content = array();
            while ($row = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
                foreach ($content_cols as $column) {
                    $content[$column] = $row[$column] ?? '';
                }
                $content[$keyName] = $row[$keyName];
                $content[$codeName] = $destination_code;
                
                $this->writeContent($content, array($keyName, $codeName));
            }
        }
    }
}
