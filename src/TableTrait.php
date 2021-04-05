<?php

namespace codesaur\DataObject;

use Exception;
use PDO;
use PDOStatement;

trait TableTrait
{
    function __destruct()
    {
        if ($this->pdo) {
            $this->pdo = null;
        }
    }
    
    function __initial()
    {
    }
    
    public function getName(): string
    {
        if (empty($this->_name)) {
            throw new Exception(__CLASS__ . ': Table name must be set!');
        }
        
        return $this->_name;
    }
    
    public function getColumns(): array
    {
        return $this->_columns ?? array();
    }
    
    public function setColumns(array $columns)
    {
        $columnSets = array();
        if (!isset($columns['id'])) {
            $columnSets['id'] = (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull();
        }

        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new Exception(__CLASS__ . ': Column should have been instance of Column class!');
            }
            
            $columnSets[$column->getName()] = $column;
        }

        $this->_columns = $columnSets;
    }
    
    public function getColumn(string $name): ?Column
    {
        return $this->_columns[$name] ?? null;
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->_columns[$name]);
    }
    
    public function getIdColumn(): Column
    {
        if (isset($this->_columns['id'])) {
            return $this->_columns['id'];
        }
        
        throw new Exception(__CLASS__ . ": Table [{$this->getName()}] definition doesn't have id column!");
    }
    
    public function create($name, $columns, $collate): bool
    {
        if (empty($name)) {
            throw new Exception(__CLASS__ . ': Table name must be provided!');
        }
        
        if (empty($columns)) {
            throw new Exception(__CLASS__ . ": Must define columns before table [$name] creation!");
        }

        $exists = $this->query('SHOW TABLES LIKE ' .  $this->quote($name));
        if ($exists->rowCount() > 0) {
            // Table already exists, no need to create new one
            return false;
        }
        
        $cols = array();
        $attributes = array();
        $hasForeignKey = false;
        foreach ($columns as $key => $column) {
            $cols[] = $column->getSyntax();
            
            if ($column->isPrimary()) {
                $attributes[] = "PRIMARY KEY (`$key`)";
            }            
            if ($column->isUnique()) {
                $attributes[] = "UNIQUE (`$key`)";
            }            
            if ($column->isAuto() && $column->isIntType()) {
                $auto_increment = 1;
            }            
            if ($column->hasForeignKey()) {
                $hasForeignKey = true;
                $foreign = $column->getForeignKey();
                if (is_array($foreign)) {
                    $foreign_key = key($foreign);
                    $foreign_ref = current($foreign);
                } else {
                    $foreign_key = $key;
                    $foreign_ref = $foreign;
                }                
                $attributes[] = "FOREIGN KEY (`$foreign_key`) REFERENCES $foreign_ref";
            }
        }
        
        $create = "CREATE TABLE `$name` (";        
        $create .= implode(', ', $cols);
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
            $this->exec('set foreign_key_checks=0');
        }
        
        if ($this->exec($create) === false) {
            throw new Exception(__CLASS__ . ": Table [$name] creation failed!");
        }
        
        return true;
    }
    
    public function createVersion($name, $version)
    {
        $exists = $this->query('SHOW TABLES LIKE ' .  $this->quote($version));
        if ($exists->rowCount() === 0) {
            if ($this->exec("CREATE TABLE $version LIKE " . $this->quote($name)) !== false) {
                return $this->exec("ALTER TABLE $version ADD v_id bigint(20) NOT NULL, ADD v_number int(11) NOT NULL") !== false;
            }
        }
        
        return false;
    }
    
    public function delete(array $condition)
    {
        if (getenv('CODESAUR_DB_KEEP_DELETE', true) == 'true'
                && $this->hasColumn('is_active')
        ) {
            return $this->deactivate($condition);
        }
        
        if (empty($condition['WHERE']) && empty($condition['LIMIT'])) {
            throw new Exception(__CLASS__ . ': Can\'t delete data without proper selection!');
        }

        $conditions = array();
        if (isset($condition['WHERE'])) {
            $conditions[] = 'WHERE ' . $condition['WHERE'];
        }
        if (isset($condition['ORDER BY'])) {
            $conditions[] = 'ORDER BY ' . $condition['ORDER BY'];
        }
        if (isset($condition['LIMIT'])) {
            $conditions[] = 'LIMIT ' . $condition['LIMIT'];
        }
        
        $idColumn = $this->getIdColumn();
        $idName = $idColumn->getName();
        $selectstmt = $this->prepare("SELECT $idName FROM {$this->getName()} " . implode(' ', $conditions));
        if (!empty($condition['VALUES'])) {
            foreach ($condition['VALUES'] as $key => $value) {
                $data_type = $this->hasColumn($key) ?
                        $this->getColumn($key)->getDataType() : PDO::PARAM_STR;
                $selectstmt->bindValue(":$key", $value, $data_type);
            }
        }
        $selectstmt->execute();
        
        $deletedIds = array();
        $deletestmt = $this->prepare("DELETE FROM {$this->getName()} WHERE $idName=:$idName");
        while ($row = $selectstmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row[$idName])) {
                continue;
            }

            $deletestmt->bindValue(":$idName", $row[$idName], $idColumn->getDataType());
            
            $deleted = $deletestmt->execute();
            if ($deletestmt->rowCount()
                    || ($deleted && $this->driverName() !== 'mysql')
            ) {
                $id = $idColumn->isIntType() ? (int)$row[$idName] : $row[$idName];
                $deletedIds[$id] = $id;
            }
        }
        
        return empty($deletedIds) ? false : $deletedIds;
    }
    
    public function deleteById($id)
    {
        $idName = $this->getIdColumn()->getName();
        $condition = array(
            'WHERE' => "$idName=:$idName",
            'VALUES' => array($idName => $id)
        );
        
        return $this->delete($condition);
    }
    
    public function deactivate(array $condition)
    {
        if (!$this->hasColumn('is_active')) {
            throw new Exception(__CLASS__ . ': Can\'t deactivate record. Table doesn\'t have an is_active column!');
        }
        
        if (empty($condition['WHERE']) && empty($condition['LIMIT'])) {
            throw new Exception(__CLASS__ . ': Can\'t deactivate data without proper selection!');
        }
        
        $conditions = array();
        if (isset($condition['WHERE'])) {
            $conditions[] = 'WHERE ' . $condition['WHERE'];
        }
        if (isset($condition['ORDER BY'])) {
            $conditions[] = 'ORDER BY ' . $condition['ORDER BY'];
        }
        if (isset($condition['LIMIT'])) {
            $conditions[] = 'LIMIT ' . $condition['LIMIT'];
        }

        $idColumn = $this->getIdColumn();
        $idName = $idColumn->getName();
        $selection = "$idName,is_active";
        
        $uniques = array();
        $sets = array('is_active=:is_active');
        foreach ($this->getColumns() as $column) {
            $uniqueName = $column->getName();
            if ($column->isUnique() && $uniqueName != $idName) {
                $uniques[] = $column;
                $sets[] = "$uniqueName=:$uniqueName";
                $selection .= ",$uniqueName";
            }
        }        
        
        $selectstmt = $this->prepare("SELECT $selection FROM {$this->getName()} " . implode(' ', $conditions));
        if (!empty($condition['VALUES'])) {
            foreach ($condition['VALUES'] as $key => $value) {
                $data_type = $this->hasColumn($key) ?
                        $this->getColumn($key)->getDataType() : PDO::PARAM_STR;
                $selectstmt->bindValue(":$key", $value, $data_type);
            }
        }
        $selectstmt->execute();

        $deactivatedIds = array();
        $deactivate = 'UPDATE ' . $this->getName();
        $deactivate .= ' SET ' . implode(', ', $sets);
        $deactivate .= " WHERE $idName=:$idName";
        $deactivatestmt = $this->prepare($deactivate);        
        while ($row = $selectstmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row[$idName])
                    || !$row['is_active']
            ) {
                continue;
            }
            
            $deactivatestmt->bindValue(':is_active', 0, PDO::PARAM_INT);
            $deactivatestmt->bindValue(":$idName", $row[$idName], $idColumn->getDataType());
            
            foreach ($uniques as $unique) {
                $uniqueName = $unique->getName();
                if ($unique->isNumericType()) {
                    $row[$uniqueName] = PHP_INT_MAX - $row[$uniqueName];
                } else {
                    $row[$uniqueName] = '[' . uniqid() . '] ' . $row[$uniqueName];
                }

                $deactivatestmt->bindValue(":$uniqueName", $row[$uniqueName], $unique->getDataType());
            }

            if ($deactivatestmt->execute()) {
                $id = $idColumn->isIntType() ? (int)$row[$idName] : $row[$idName];
                $deactivatedIds[$id] = $id;
            }
        }
        
        return empty($deactivatedIds) ? false : $deactivatedIds;
    }
    
    public function driverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    public function databaseName(): ?string
    {
        return $this->query('select database()')->fetchColumn();
    }    
    
    public function quote(string $string, int $parameter_type = PDO::PARAM_STR): string
    {
        return $this->pdo->quote($string, $parameter_type);
    }

    public function prepare(string $statement, array $driver_options = array()): PDOStatement
    {
        return $this->pdo->prepare($statement, $driver_options);
    }

    public function exec(string $statement)
    {
        return $this->pdo->exec($statement);
    }

    public function query(string $statement): PDOStatement
    {
        return $this->pdo->query($statement);
    }

    public function lastInsertId(string $name = NULL): string
    {
        return $this->pdo->lastInsertId($name);
    }
}
