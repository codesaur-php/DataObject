<?php

namespace codesaur\DataObject;

trait StatementTrait
{
    use PDOTrait;
    
    public function createTable(string $table, array $columns, ?string $collate)
    {
        $references = [];
        $columnSyntaxes = [];
        foreach ($columns as $key => $column) {
            if (!$column instanceof Column) {
                continue;
            }
            
            $columnSyntaxes[] = $column->getSyntax();
            
            if ($column->isPrimary()) {
                $references[] = "PRIMARY KEY ($key)";
            }            
            if ($column->isUnique()) {
                $references[] = "UNIQUE ($key)";
            }            
            if ($column->isAuto() && $column->isInt()) {
                $auto_increment = 1;
            }
        }
        
        $create = "CREATE TABLE $table (";
        $create .= implode(', ', $columnSyntaxes);
        if (!empty($references)) {
            $create .= ', ';
            $create .= implode(', ', $references);
        }
        $create .= ')';
        if (strtolower($this->driverName()) == 'mysql') {
             $create .= ' ENGINE=InnoDB';
        }
        if (!empty($collate)) {
            $create .= " COLLATE=$collate";
        }
        if (isset($auto_increment)) {
            $create .= " AUTO_INCREMENT=$auto_increment";
        }
        
        if ($this->exec($create) === false) {
            $error_info = $this->pdo->errorInfo();
            throw new \Exception(
                __CLASS__ . ": Table [$table] creation failed! " .  implode(': ', $error_info),
                (int) (is_int($error_info[1] ?? null) ? $error_info[1] : $this->pdo->errorCode()));
        }
    }
    
    public function createTableVersion(string $originalTable, string $versionTable)
    {
        if ($this->exec("CREATE TABLE $versionTable LIKE " . $this->quote($originalTable)) === false) {
            $error_info = $this->pdo->errorInfo();
            throw new \Exception(
                __CLASS__ . ": Version table [$versionTable] creation failed! " .  implode(': ', $error_info),
                (int) (is_int($error_info[1] ?? null) ? $error_info[1] : $this->pdo->errorCode()));
        }
        
        if ($this->exec("ALTER TABLE $versionTable ADD v_id bigint(8) NOT NULL, ADD v_number int(4) NOT NULL") === false) {
            $error_info = $this->pdo->errorInfo();
            throw new \Exception(
                __CLASS__ . ": Table [$versionTable] version columns creation failed!  " .  implode(': ', $error_info),
                (int) (is_int($error_info[1] ?? null) ? $error_info[1] : $this->pdo->errorCode()));
        }
    }
    
    public function selectFrom(string $table, string $selection, array $condition): \PDOStatement
    {
        $select = "SELECT $selection FROM $table";
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
        
        throw new \Exception(
            __CLASS__ . ": Can't select from [$table]! " .  implode(': ', $stmt->errorInfo()),
            (int) (is_int($stmt->errorInfo()[1] ?? null) ? $stmt->errorInfo()[1] : $stmt->errorCode()));
    }
}
