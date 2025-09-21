<?php

namespace codesaur\DataObject;

abstract class LocalizedModel
{
    use TableTrait;
    
    protected readonly array $contentColumns; // Content table columns

    public function setTable(string $name)
    {
        $this->name = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        $table = $this->getName();
        $columns = $this->getColumns();
        if (empty($columns)
            || empty($this->getContentColumns())
        ) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", 1113);
        } elseif (!$this->hasColumn('id')
            || !$this->getColumn('id')->isInt()
            || !$this->getColumn('id')->isPrimary()
        ) {
            throw new \Exception(__CLASS__ . ": Table [$table] must have primary auto increment id column!");
        } elseif ($this->hasTable($table)) {
            return;
        }
        $this->createTable($table, $columns);

        $contentTable = $this->getContentName();
        $this->createTable($contentTable, $this->getContentColumns());
        $this->exec("ALTER TABLE $contentTable ADD FOREIGN KEY (parent_id) REFERENCES $table(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->__initial();
    }

    public function getContentName(): string
    {
        return $this->getName() . '_content';
    }

    public function getContentColumns(): array
    {
        return $this->contentColumns ?? [];
    }

    public function getContentColumn(string $name): Column
    {
        if (isset($this->contentColumns[$name])
            && $this->contentColumns[$name] instanceof Column
        ) {
            return $this->contentColumns[$name];
        }
        
        throw new \Exception(__CLASS__ . ": Table [{$this->getContentName()}] definition doesn't have localized content column named [$name]", 1054);
    }

    public function setContentColumns(array $columns)
    {
        $id = $this->getColumn('id');
        $contentColumns = [
            'id' => (new Column('id', $id->getType()))->primary(),
            'parent_id' => (new Column('parent_id', $id->getType()))->notNull(),
            'code' => new Column('code', 'varchar', 6)
        ];
        
        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new \Exception(__CLASS__ . ': Column should have been instance of Column class!');
            } elseif (isset($contentColumns[$column->getName()])) {
                throw new \Exception(__CLASS__ . ": Content table already has predefined column named [{$column->getName()}]");
            } elseif ($column->isUnique()) {
                throw new \Exception(__CLASS__ . ": Content table forbidden to contain unique column [{$column->getName()}]");
            }
            
            $contentColumns[$column->getName()] = $column;
        }

        $this->contentColumns = $contentColumns;
    }

    public function insert(array $record, array $content): array|false
    {
        $contentTable = $this->getContentName();
        if (empty($content)) {
            throw new \InvalidArgumentException(__CLASS__ . "[$contentTable}]: Can't insert record when localized content is empty!");
        }
        
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = \implode(', ', $column);
        $values = \implode(', ', $param);
        
        $table = $this->getName();
        $query = "INSERT INTO $table($columns) VALUES($values)";
        if ($this->getDriverName() == 'pgsql') {
            $query .= ' RETURNING id';
        }
        $insert = $this->prepare($query);
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        
        if (!$insert->execute()) {
            return false;
        }
        
        if ($this->getDriverName() == 'pgsql') {
            $id = $insert->fetch(\PDO::FETCH_ASSOC)['id'];
        } else {
            $id = (int) ($record['id'] ?? $this->pdo->lastInsertId('id'));
        }
        
        foreach ($content as $code => $data) {
            $content_field = $content_value = [];
            foreach (\array_keys($data) as $key) {
                $content_field[] = $key;
                $content_value[] = ":$key";
            }
            $data['parent_id'] = $id;
            $data['code'] = $code;
            $content_field[] = 'parent_id';
            $content_value[] = ':parent_id';
            $content_field[] = 'code';
            $content_value[] = ':code';

            $fields = \implode(', ', $content_field);
            $values = \implode(', ', $content_value);
            $content_stmt = $this->prepare("INSERT INTO $contentTable($fields) VALUES($values)");
            foreach ($data as $key => $value) {
                $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
            }
            
            try {
                if (!$content_stmt->execute()) {
                    $error_info = $content_stmt->errorInfo();
                    if (\is_numeric($error_info[1] ?? null)) {
                        $error_code = (int) $error_info[1];
                    } elseif (\is_numeric($content_stmt->errorCode())) {
                        $error_code = (int) $content_stmt->errorCode();
                    } else {
                        $error_code = 0;
                    }
                    throw new \Exception(\implode(': ', $error_info), $error_code);
                }
            } catch (\Throwable $e) {
                $this->query("DELETE FROM $table WHERE id=$id");
                throw new \Exception(__CLASS__ . ": Failed to insert content on table [$contentTable] " . $e->getMessage(), $e->getCode());
            }
        }
        
        return $this->getRowWhere(['p.id' => $id]) ?? false;
    }
    
    public function updateById(int $id, array $record, array $content): array|false
    {
        $table = $this->getName();
        $row = $current_record = $this->getRowWhere(['p.id' => $id]) ?? [];
        if (!empty($record)) {
            $set = [];
            foreach (\array_keys($record) as $name) {
                $set[] = "$name=:$name";
            }
            $sets = \implode(', ', $set);
            $update_primary = $this->prepare("UPDATE $table SET $sets WHERE id=:old_id");
            $update_primary->bindValue(':old_id', $id, \PDO::PARAM_INT);
            foreach ($record as $name => $value) {
                $update_primary->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
                $row[$name] = $value;
            }
            if (!$update_primary->execute()) {
                return false;
            }
        } elseif (empty($content)) {
            throw new \Exception(__CLASS__ . ': Failed to update by id! No data provided!');
        }
        
        $contentTable = $this->getContentName();
        $content_select = $this->prepare("SELECT id FROM $contentTable WHERE parent_id=:parent_id AND code=:code LIMIT 1");
        $parent_id = $record['id'] ?? $id;
        foreach ($content as $code => $value) {
            foreach (\array_keys($value) as $key) {
                if ($key == 'parent_id' || $key == 'code') {
                    unset($value);
                }
            }
            if (empty($value)) {
                continue;
            }
            
            try {
                $content_select->bindValue(':parent_id', $parent_id, \PDO::PARAM_INT);
                $content_select->bindValue(':code', $code,  \PDO::PARAM_STR);
                if (!$content_select->execute()
                    || $content_select->rowCount() < 1
                ) {
                    // No localized record for code [$code] found!
                    $column = ['code'];
                    $param = [':code'];
                    foreach (\array_keys($value) as $key) {
                        $column[] = $key;
                        $param[] = ":$key";
                    }
                    $columns = \implode(', ', $column);
                    $values = \implode(', ', $param);
                    $content_stmt = $this->prepare("INSERT INTO $contentTable(parent_id,$columns) VALUES($parent_id,$values)");
                    $content_stmt->bindValue(":code", $code, \PDO::PARAM_STR);
                    foreach ($value as $key => $var) {
                        $content_stmt->bindValue(":$key", $var, $this->getContentColumn($key)->getDataType());
                        $row['localized'][$key][$code] = $var;
                    }
                } else {
                    // Updating existing localized record for code [$code]!
                    $content_row = $content_select->fetch(\PDO::FETCH_ASSOC);
                    $content_set = [];
                    foreach (\array_keys($value) as $n) {
                        $content_set[] = "$n=:$n";
                    }
                    $content_sets = \implode(', ', $content_set);
                    $content_stmt = $this->prepare("UPDATE $contentTable SET $content_sets WHERE id={$content_row['id']}");
                    foreach ($value as $key => $var) {
                        $content_stmt->bindValue(":$key", $var, $this->getContentColumn($key)->getDataType());
                        $row['localized'][$key][$code] = $var;
                    }
                }
                if (!$content_stmt->execute()) {
                    $error_info = $content_stmt->errorInfo();
                    if (\is_numeric($error_info[1] ?? null)) {
                        $error_code = (int) $error_info[1];
                    } elseif (\is_numeric($content_stmt->errorCode())) {
                        $error_code = (int) $content_stmt->errorCode();
                    } else {
                        $error_code = 0;
                    }
                    throw new \Exception(\implode(': ', $content_stmt->errorInfo()), $error_code);
                }
            } catch (\Throwable $e) {
                throw new \Exception(__CLASS__ . ": Failed to update content on table [$contentTable]! " . $e->getMessage(), $e->getCode());
            }
        }
        return $row;
    }
    
    public function select(string $selection = '*', array $condition = []): \PDOStatement
    {
        if ($selection == '*') {
            $fields = [];
            foreach (\array_keys($this->getColumns()) as $column) {
                $fields[] = "p.$column as p_$column";
            }
            foreach (\array_keys($this->getContentColumns()) as $column) {
                $fields[] = "c.$column as c_$column";
            }
            $selection = \implode(', ', $fields);
        }
        
        $table = $this->getName();
        $contentTable = $this->getContentName();
        $condition['INNER JOIN'] = "$contentTable c ON p.id=c.parent_id";
        return $this->selectStatement("$table p", $selection, $condition);
    }

    public function getRows(array $condition = []): array
    {
        $rows = [];
        $pdostmt = $this->select('*', $condition);
        $content_KeyColumns = ['id', 'parent_id', 'code'];
        while ($data = $pdostmt->fetch(\PDO::FETCH_ASSOC)) {
            $p_id = (int) $data['p_id'];
            if (!isset($rows[$p_id]['id'])) {
                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();
                    $rows[$p_id][$columnName] = $data["p_$columnName"];
                }
            }
            
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();
                if (!\in_array($ccolumnName, $content_KeyColumns)) {
                    $rows[$p_id]['localized'][$ccolumnName][$data['c_code']] = $data["c_$ccolumnName"];
                }
            }
        }
        return $rows;
    }
    
    public function getRow(array $condition): array|null
    {
        $row = [];
        $c_codeName = 'c_code';
        $content_KeyColumns = ['id', 'parent_id', 'code'];
        $stmt = $this->select('*', $condition);            
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            foreach ($this->getColumns() as $column) {
                $columnName = $column->getName();
                if (isset($data["p_$columnName"])) {
                    $row[$columnName] = $data["p_$columnName"];
                }
            }
            foreach ($this->getContentColumns() as $ccolumn) {
                $ccolumnName = $ccolumn->getName();
                if (!\in_array($ccolumnName, $content_KeyColumns)) {
                    if (isset($data["c_$ccolumnName"])) {
                        $row['localized'][$ccolumnName][$data[$c_codeName]] = $data["c_$ccolumnName"];
                    }
                }
            }
        }
        return !empty($row) ? $row : null;
    }


    public function getRowWhere(array $with_values): array|null
    {
        $count = 1;
        $params = [];
        $wheres = [];
        foreach ($with_values as $key => $value) {
            $params[":$count"] = $value;
            $wheres[] = "$key=:$count";
            $count++;
        }
        $clause = \implode(' AND ', $wheres);
        if (empty($clause)) {
            return null;
        }        
        return $this->getRow([
            'WHERE' => $clause,
            'PARAM' => $params
        ]);
    }
}
