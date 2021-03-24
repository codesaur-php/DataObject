<?php

namespace codesaur\DataObject;

use Exception;
use PDO;
use PDOStatement;

class Table
{
    protected $pdo;     // PHP Data Object
    
    protected $name;    // Table name
    protected $columns; // Table columns
    
    function __construct(PDO $conn)
    {
        $this->pdo = $conn;
    }
    
    function __destruct()
    {
        $this->pdo = null;
    }
    
    public function getDriverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    public function getDatabaseName(): ?string
    {
        return $this->query('select database()')->fetchColumn();
    }    
    
    public function getName()
    {
        if (empty($this->name)) {
            throw new Exception('Table name must be set!');
        }
        
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
    }

    final public function getVersionName()
    {
        return $this->getName() . '_version';
    }
    
    final public function getColumns(): array
    {
        return $this->columns;
    }
    
    public function setColumns(array $columns)
    {
        $this->columns = array();
        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new Exception('Column should have been instance of Column class!');
            }
            
            $this->columns[$column->getName()] = $column;
        }
        
        if (!isset($this->columns['id'])) {
            $this->columns['id'] = (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull();
        }
    }
    
    final public function getColumn(string $name): ?Column
    {
        return $this->getColumns()[$name] ?? null;
    }

    final public function hasColumn(string $name): bool
    {
        return isset($this->getColumns()[$name]);
    }
    
    final public function getIdColumn(): Column
    {
        return $this->getColumn('id');
    }
    
    public function setCreateTable(?string $name = null, $collate = null): bool
    {
        if (!empty($name)) {
            $this->setName($name);
        }
        
        if (empty($this->columns)) {
            throw new Exception('Must define columns before table creation!');
        }

        $exists = $this->query('SHOW TABLES LIKE ' .  $this->quote($this->getName()));
        if ($exists->rowCount() > 0) {
            // Table already exists, no need to create new one
            return false;
        }
        
        $columns = array();
        $attributes = array();
        $hasForeignKey = false;        
        foreach ($this->getColumns() as $key => $column) {
            $columns[] = $column->getSyntax();
            
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
        
        $query = "CREATE TABLE `{$this->getName()}` (";        
        $query .= implode(', ', $columns);
        if (!empty($attributes)) {
            $query .= ', ';
            $query .= implode(', ', $attributes);
        }        
        $query .= ')';
        if (strtolower($this->getDriverName()) === 'mysql') {
             $query .= ' ENGINE=InnoDB';
        }
        if ($collate) {
            $query .= " COLLATE=$collate";
        }
        if (isset($auto_increment)) {
            $query .= " AUTO_INCREMENT=$auto_increment";
        }
        
        if ($hasForeignKey) {
            $this->exec('set foreign_key_checks=0');
        }
        
        return $this->exec($query) !== false;
    }
    
    public function setVersionTable()
    {
        $version = $this->quote($this->getVersionName());        
        $exists = $this->query("SHOW TABLES LIKE $version");
        if ($exists->rowCount() === 0) {
            if ($this->exec("CREATE TABLE $version LIKE {$this->quote($this->getName())}") !== false) {
                return $this->exec("ALTER TABLE $version ADD v_id bigint(20) NOT NULL, ADD v_number int(11) NOT NULL") !== false;
            }
        }
        
        return false;
    }
    
    final public function select(string $selection = '*', array $by_record = [], array $condition = []): PDOStatement
    {
        $query = "SELECT $selection FROM " . $this->getName();
        if (isset($condition['JOIN'])) {
            $query .= ' ' . $condition['JOIN'];
        }
        
        $wheres = array();
        foreach (array_keys($by_record) as $column) {
            if ($this->hasColumn($column)) {
                $wheres[] = "$column=:$column";
            } else {
                unset($by_record[$column]);
            }
        }
        if (isset($condition['WHERE'])) {
            $wheres[] = $condition['WHERE'];
        }        
        if (!empty($wheres)) {
            $query .= ' WHERE ' . implode(' AND ', $wheres);
        }
        
        if (isset($condition['ORDER BY'])) {
            $query .= ' ORDER BY ' . $condition['ORDER BY'];
        }
        
        if (isset($condition['LIMIT'])) {
            $query .= ' LIMIT ' . $condition['LIMIT'];
        }
        
        $pdostmt = $this->prepare($query);
        foreach ($by_record as $key => $value) {
            $pdostmt->bindValue(":$key", $value, $this->getColumn($key)->getDataType());
        }
        $pdostmt->execute();

        return $pdostmt;
    }
    
    public function insert(array $record)
    {
        $fields = $values = array();
        foreach (array_keys($record) as $name) {
            if (!$this->hasColumn($name)) {
                unset($record[$name]);
                continue;
            }
            
            $fields[] = $name;
            $values[] = $this->getColumn($name)->getBindName();
        }
        
        $query = 'INSERT INTO ' . $this->getName();
        $query .= ' (' . implode(', ', $fields) . ')';
        $query .= ' VALUES (' . implode(', ', $values) . ')';
        
        $stmt = $this->prepare($query);        
        foreach ($record as $key => $value) {
            $stmt->bindValue($this->getColumn($key)->getBindName(), $value, $this->getColumn($key)->getDataType());
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
    
    public function update(array $record, array $where = [], string $condition = '')
    {
        $idName = $this->getIdColumn()->getName();
        if (empty($where) && empty($condition)) {
            if (!isset($record[$idName])) {
                throw new Exception('no primary index to update');
            }
            
            $where = array($idName);
        }
        
        $sets = array();
        $wheres = array();
        foreach (array_keys($record) as $name) {
            if (!$this->hasColumn($name)) {
                unset($record[$name]);
                continue;
            }

            if (isset($where[$name])) {
                $wheres[] = $name . '=' . $this->getColumn($name)->getBindName();
            } else {
                $sets[] =  $name . '=' . $this->getColumn($name)->getBindName();
            }
        }
        
        if (!empty($condition)) {
            $wheres[] = $condition;
        }

        if (empty($sets)) {
            throw new Exception('no record set for update');
        }

        if (empty($wheres)) {
            throw new Exception('no conditions set to update');
        }
        
        $query = 'UPDATE ' . $this->getName();
        $query .= ' SET ' . implode(', ', $sets);
        $query .= ' WHERE ' . implode(' AND ', $wheres);
        $stmt = $this->prepare($query);

        foreach ($record as $name => $value) {
            $stmt->bindValue($this->getColumn($name)->getBindName(), $value, $this->getColumn($name)->getDataType());
        }
        
        if ($stmt->execute()) {
            return $record[$idName] ?? $stmt->rowCount();
        }
        
        return false;
    }
    
    public function delete(array $by_record)
    {
        if (getenv('CODESAUR_DB_KEEP_DATA', true) == 'true'
                && $this->hasColumn('is_active')
        ) {
            return $this->deactivate($by_record);
        }
        
        $wheres = array();
        foreach (array_keys($by_record) as $column) {
            if ($this->hasColumn($column)) {
                $wheres[] = "$column=:$column";
            } else {
                unset($by_record[$column]);
            }
        }

        if (empty($wheres)) {
            throw new Exception('no conditions set to delete');
        }
        
        $pdostmt = $this->prepare('DELETE FROM ' . $this->getName() . ' WHERE ' . implode(' AND ', $wheres));
        foreach ($by_record as $key => $value) {
            $pdostmt->bindValue(":$key", $value, $this->getColumn($key)->getDataType());
        }
        
        return $pdostmt->execute();
    }
    
    public function getByID($value)
    {
        return $this->getRow(array($this->getIdColumn()->getName() => $value));
    }

    public function getRow(array $by_record)
    {
        foreach (array_keys($by_record) as $column) {
            if (!$this->hasColumn($column)) {
                unset($by_record[$column]);
            }
        }
        
        if (!empty($by_record)) {
            $pdostmt = $this->select('*', $by_record);
            if ($pdostmt->rowCount() === 1) {
                return $pdostmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        return null;
    }

    public function getRows(array $condition = []): array
    {
        if (empty($condition)) {
            $condition['ORDER BY'] =  $this->getIdColumn()->getName();
        }
        
        $count = 0;
        $rows = array();
        $idName = $this->getIdColumn()->getName();
        $pdostmt = $this->select('*', array(), $condition);
        while ($data = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($this->getColumns() as $column) {
                $value = $data[$column->getName()] ?? $column->getDefault();
                $rows[$data[$idName] ?? ++$count][$column->getName()] = $value;
            }
        }
        
        return $rows;
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
    
    private function deactivate(array $by_record)
    {
        foreach (array_keys($by_record) as $column) {
            if (!$this->hasColumn($column)) {
                unset($by_record[$column]);
            }
        }
        
        if (empty($by_record)) {
            return false;
        }
        
        $pdostmt = $this->select('*', $by_record);
        $result = $pdostmt->fetch(PDO::FETCH_ASSOC);
        if ($result === false) {
            return false;
        }
        
        $idColumn = $this->getIdColumn();
        if (!isset($result[$idColumn->getName()])) {
            return false;
        }
            
        $result['is_active'] = 0;
        $id = $result[$idColumn->getName()];

        foreach ($this->getColumns() as $column) {
            if ($column->isUnique()) {
                if ($column->isNumeric()) {
                    if ($column->getName() != $idColumn->getName()) {
                        $result[$column->getName()] = PHP_INT_MAX - $result[$column->getName()];
                    }
                } else {
                    $result[$column->getName()] = '[' . uniqid() . '] ' . $result[$column->getName()];
                }
            }
        }

        if (!$idColumn->isNumeric()) {
            $id = $this->quote($id);
        }

        return $this->update($result, array(), $idColumn->getName() . '=' . $id);    
    }
}
