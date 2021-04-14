<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

use Exception;

trait TableTrait
{
    use PDOTrait;
    
    public function create(string $name, array $columns, $collate)
    {
        $attributes = array();
        $hasForeignKey = false;
        $columnSyntaxes = array();
        foreach ($columns as $key => $column) {
            if (!$column instanceof Column) {
                continue;
            }
            
            $columnSyntaxes[] = $column->getSyntax();
            
            if ($column->isPrimary()) {
                $attributes[] = "PRIMARY KEY (`$key`)";
            }            
            if ($column->isUnique()) {
                $attributes[] = "UNIQUE (`$key`)";
            }            
            if ($column->isAuto() && $column->isInt()) {
                $auto_increment = 1;
            }
            
            $foreignKey = $column->getForeignKey();
            if (!empty($foreignKey)) {
                $hasForeignKey = true;        
                $attributes[] = $foreignKey;
            }
        }
        
        $create = "CREATE TABLE `$name` (";
        $create .= implode(', ', $columnSyntaxes);
        if (!empty($attributes)) {
            $create .= ', ';
            $create .= implode(', ', $attributes);
        }        
        $create .= ')';
        if (strtolower($this->driverName()) === 'mysql') {
             $create .= ' ENGINE=InnoDB';
        }
        if (!empty($collate)) {
            $create .= " COLLATE=$collate";
        }
        if (isset($auto_increment)) {
            $create .= " AUTO_INCREMENT=$auto_increment";
        }
        
        if ($hasForeignKey) {
            $this->setForeignKeyChecks(false);
        }
        
        if ($this->exec($create) === false) {
            throw new Exception(__CLASS__ . ": Table [$name] creation failed!");
        } elseif ($hasForeignKey) {
            $this->setForeignKeyChecks();
        }
    }
    
    public function createVersion(string $originalName, string $versionName)
    {
        if ($this->exec("CREATE TABLE $versionName LIKE " . $this->quote($originalName)) === false) {
            throw new Exception(__CLASS__ . ": Version table [$versionName] creation failed!");
        }
        
        if ($this->exec("ALTER TABLE $versionName ADD v_id bigint(20) NOT NULL, ADD v_number int(11) NOT NULL") === false) {
            throw new Exception(__CLASS__ . ": Table [$versionName] version columns creation failed!");
        }
    }
    
    public function selectStatement(string $name, string $selection, array $condition): PDOStatement
    {
        $select = "SELECT $selection FROM $name";
        if (isset($condition['JOIN'])) {
            $select .= ' JOIN ' . $condition['JOIN'];
        }
        if (isset($condition['CROSS JOIN'])) {
            $select .= ' CROSS JOIN ' . $condition['CROSS JOIN'];
        }
        if (isset($condition['INNER JOIN'])) {
            $select .= ' INNER JOIN ' . $condition['INNER JOIN'];
        }
        if (isset($condition['LEFT JOIN'])) {
            $select .= ' LEFT JOIN ' . $condition['LEFT JOIN'];
        }
        if (isset($condition['RIGHT JOIN'])) {
            $select .= ' RIGHT JOIN ' . $condition['RIGHT JOIN'];
        }
        if (isset($condition['WHERE'])) {
            $select .= ' WHERE ' . $condition['WHERE'];
        }
        if (isset($condition['GROUP BY'])) {
            $select .= ' GROUP BY ' . $condition['ORDER BY'];
        }
        if (isset($condition['HAVING'])) {
            $select .= ' HAVING ' . $condition['ORDER BY'];
        }
        if (isset($condition['ORDER BY'])) {
            $select .= ' ORDER BY ' . $condition['ORDER BY'];
        }
        if (isset($condition['LIMIT'])) {
            $select .= ' LIMIT ' . $condition['LIMIT'];
        }

        $stmt = $this->prepare($select);
        if ($stmt->execute($condition['PARAM'] ?? null)) {
            return $stmt;
        }

        throw new Exception(__CLASS__ . ": Can't select from [$name]");
    }
}
