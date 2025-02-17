<?php

namespace codesaur\DataObject;

trait TableTrait
{

    use PDOTrait;

    /**
     * The sql table name.
     *
     * @var string
     */
    protected readonly string $name;

    /**
     * The sql table columns definitions.
     *
     * @var array
     */
    protected readonly array $columns;

    public abstract function __construct(\PDO $pdo);

    protected abstract function __initial();

    public function __destruct()
    {
        unset($this->pdo);
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            throw new \Exception(__CLASS__ . ': Table name must be provided', 1103);
        }

        return $this->name;
    }

    public function setTable(string $name)
    {
        $this->name = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);

        $table = $this->getName();
        if (empty($this->columns)) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", 1113);
        } elseif ($this->hasTable($table)) {
            return;
        }

        $this->createTable($table, $this->columns);
        $this->__initial();
    }

    public function getColumns(): array
    {
        return $this->columns ?? throw new \Exception("Table [$this->name] doesn't have columns definition!");
    }

    public function setColumns(array $columns)
    {
        $columnSets = [];
        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new \Exception(__CLASS__ . ': Column should have been instance of Column class');
            }
            $columnSets[$column->getName()] = $column;
        }

        $this->columns = $columnSets;
    }

    public function getColumn(string $name): Column
    {
        if ($this->hasColumn($name)) {
            return $this->columns[$name];
        }

        throw new \Exception(__CLASS__ . ": Table [$this->name] definition doesn't have column named [$name]", 1054);
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    public function deleteById(int $id): bool
    {
        $table = $this->getName();
        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception("(deleteById): Table [$table] must have primary auto increment id column!");
        }
        
        $delete = $this->prepare("DELETE FROM $table WHERE id=$id");
        return $delete->execute() && $delete->rowCount() > 0;
    }
    
    public function deactivateById(int $id, array $record = []): bool
    {
        $table = $this->getName();
        if (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception("(deactivateById): Table [$table] must have primary auto increment id column!");
        }
        
        if (!$this->hasColumn('is_active')
            || !$this->getColumn('is_active')->isInt()
        ) {
            throw new \Exception("(deactivateById): Table [$table] must have an is_active column!");
        }
        
        $selection = 'is_active';
        $set = ['is_active=:is_active'];
        $uniques = [];
        foreach ($this->getColumns() as $column) {
            $uniqueName = $column->getName();
            if ($column->isUnique() && $uniqueName != 'id') {
                $uniques[] = $column;
                $selection .= ", $uniqueName";
                $set[] = "$uniqueName=:$uniqueName";
            }
        }
        foreach (\array_keys($record) as $name) {
            $selection .= ", $name";
            $set[] = "$name=:$name";
        }
        $select = $this->query("SELECT $selection FROM $table WHERE id=$id");
        $row = $select->fetch(\PDO::FETCH_ASSOC);
        if (($row['is_active'] ?? 0) == 0) {
            return false;
        }
        $sets = \implode(', ', $set);
        $update = $this->prepare("UPDATE $table SET $sets WHERE id=$id");
        $update->bindValue(':is_active', 0, \PDO::PARAM_INT);
        foreach ($uniques as $unique) {
            $uniqueName = $unique->getName();
            if ($unique->isNumeric()) {
                $row[$uniqueName] = -$row[$uniqueName];
            } else {
                $row[$uniqueName] = '[' . \uniqid() . '] ' . $row[$uniqueName];
            }
            $update->bindValue(":$uniqueName", $row[$uniqueName], $unique->getDataType());
        }
        foreach ($record as $name => $value) {
            $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        return $update->execute();
    }

    protected final function createTable(string $table, array $columns)
    {
        $references = [];
        $columnSyntaxes = [];
        foreach ($columns as $key => $column) {
            if (!$column instanceof Column) {
                continue;
            }

            $columnSyntaxes[] = $this->getSyntax($column);

            if ($column->isUnique()) {
                $references[] = "UNIQUE ($key)";
            }
        }

        $create = "CREATE TABLE $table (";
        $create .= \implode(', ', $columnSyntaxes);
        if (!empty($references)) {
            $create .= ', ';
            $create .= \implode(', ', $references);
        }
        $create .= ')';
        
        if ($this->exec($create) === false) {
            $error_info = $this->pdo->errorInfo();
            if (\is_numeric($error_info[1] ?? null)) {
                $error_code = (int) $error_info[1];
            } elseif (\is_numeric($this->pdo->errorCode())) {
                $error_code = (int) $this->pdo->errorCode();
            } else {
                $error_code = 0;
            }
            throw new \Exception(__CLASS__ . ": Table [$table] creation failed! " . \implode(': ', $error_info), $error_code);
        }
    }

    public function selectStatement(string $fromTable, string $selection= '*', array $condition = []): \PDOStatement
    {
        $select = "SELECT $selection FROM $fromTable";
        if (!empty($condition['JOIN'])) {
            $select .= ' JOIN ' . $condition['JOIN'];
        }
        if (!empty($condition['CROSS JOIN'])) {
            $select .= ' CROSS JOIN ' . $condition['CROSS JOIN'];
        }
        if (!empty($condition['INNER JOIN'])) {
            $select .= ' INNER JOIN ' . $condition['INNER JOIN'];
        }
        if (!empty($condition['LEFT JOIN'])) {
            $select .= ' LEFT JOIN ' . $condition['LEFT JOIN'];
        }
        if (!empty($condition['RIGHT JOIN'])) {
            $select .= ' RIGHT JOIN ' . $condition['RIGHT JOIN'];
        }
        if (!empty($condition['WHERE'])) {
            $select .= ' WHERE ' . $condition['WHERE'];
        }
        if (!empty($condition['GROUP BY'])) {
            $select .= ' GROUP BY ' . $condition['ORDER BY'];
        }
        if (!empty($condition['HAVING'])) {
            $select .= ' HAVING ' . $condition['HAVING'];
        }
        if (!empty($condition['ORDER BY'])) {
            $select .= ' ORDER BY ' . $condition['ORDER BY'];
        }
        if (!empty($condition['LIMIT'])) {
            $select .= ' LIMIT ' . $condition['LIMIT'];
        }

        $stmt = $this->prepare($select);
        if ($stmt->execute($condition['PARAM'] ?? null)) {
            return $stmt;
        }

        $error_info = $stmt->errorInfo();
        if (\is_numeric($error_info[1] ?? null)) {
            $error_code = (int) $error_info[1];
        } elseif (\is_numeric($stmt->errorCode())) {
            $error_code = (int) $stmt->errorCode();
        } else {
            $error_code = 0;
        }
        throw new \Exception(__CLASS__ . ": Can't select from [$fromTable]! " . \implode(': ', $error_info), $error_code);
    }

    private function getSyntax(Column $column): string
    {
        $str = $column->getName();
        
        if ($column->isPrimary()) {
            $column->notNull()->auto();
        }

        $type = $column->getType();
        if ($this->getDriverName() == 'pgsql') {
            switch ($type) {
                case 'int8':
                    $type = 'bigint';
                    break;
                case 'integer':
                case 'mediumint':
                    $type = 'int';
                    break;
                case 'tinyint':
                    $type = 'smallint';
                    break;
                case 'datetime':
                    $type = 'timestamp';
                    break;
                case 'tinytext':
                case 'mediumtext':
                case 'longtext':
                    $type = 'text';
                    break;
            }
            if ($column->isAuto()) {
                if ($type == 'bigint') {
                    $type = 'bigserial';
                } elseif ($type == 'int') {
                    $type = 'serial';
                } elseif ($type == 'smallint') {
                    $type = 'smallserial';
                }
            }
        } else {
            switch ($type) {
                case 'bigserial':
                    $type = 'bigint';
                    break;
                case 'serial':
                    $type = 'int';
                    break;
                case 'smallserial':
                    $type = 'smallint';
                    break;
                case 'timestamptz':
                    $type = 'timestamp';
                    break;
            }
        }
        $str .= " $type";

        $length = $column->getLength();
        if (!empty($length)) {
            $str .= "($length)";
        }
        
        if ($column->isNull()) {
            $str .= ' NULL';
        } else {
            $str .= ' NOT NULL';
        }
        $default = $column->getDefault();
        if ($default !== null) {
            $str .= ' DEFAULT ';
            if ($column->isNumeric()) {
                $str .= $default;
            } else {
                $str .= $this->quote($default);
            }
        }

        if ($column->isPrimary()) {
            $str .= ' PRIMARY KEY';
        }

        if ($column->isAuto() && $this->getDriverName() == 'mysql') {
            $str .= ' AUTO_INCREMENT';
        }

        return $str;
    }
}
