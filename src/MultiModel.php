<?php

namespace codesaur\DataObject;

abstract class MultiModel
{
    use TableTrait;
    
    protected readonly array $contentColumns; // Content table columns

    public function setTable(string $name, ?string $collate = null)
    {
        $this->name = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        $table = $this->getName();
        $columns = $this->getColumns();
        if (empty($columns) || empty($this->getContentColumns())) {
            throw new \Exception(__CLASS__ . ": Must define columns before table [$table] set", 1113);
        } elseif ($this->hasTable($table)) {
            return;
        }
        
        $this->createTable($table, $columns, $collate);

        $contentTable = $this->getContentName();
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $this->createTable($contentTable, $this->getContentColumns(), $collate);
        $this->exec("ALTER TABLE $contentTable ADD FOREIGN KEY ($keyName) REFERENCES $table($idName) ON DELETE CASCADE ON UPDATE CASCADE");
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
        
        throw new \Exception(__CLASS__ . ": Table [{$this->getContentName()}] definition doesn't have content column named [$name]", 1054);
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
        $pid = clone $this->getIdColumn();

        $contentColumns = [
            'id' => (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            'parent_id' => (new Column('parent_id', $pid->getType(), $pid->getLength()))->notNull(),
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

    public function insert(array $record, array $content): int|string|false
    {
        $contentTable = $this->getContentName();
        if (empty($content)) {
            throw new \InvalidArgumentException(__CLASS__ . "[$contentTable}]: Can't insert record when content is empty!");
        }

        if ($this->hasColumn('created_at')
            && !isset($record['created_at'])
        ) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }

        if ($this->hasColumn('created_by')
            && !isset($record['created_by'])
            && \getenv('CODESAUR_ACCOUNT_ID', true)
        ) {
            $record['created_by'] = \getenv('CODESAUR_ACCOUNT_ID', true);
        }
        
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = \implode(', ', $column);
        $values = \implode(', ', $param);
        
        $table = $this->getName();
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
        $insertId = $idColumn->isInt() ? (int) $idRaw : $idRaw;
        
        $keyName = $this->getKeyColumn()->getName();
        $codeName = $this->getCodeColumn()->getName();
        foreach ($content as $code => $data) {
            $content_field = $content_value = [];
            foreach (\array_keys($data) as $key) {
                $content_field[] = $key;
                $content_value[] = ":$key";
            }
            $data[$keyName] = $insertId;
            $data[$codeName] = $code;
            $content_field[] = $keyName;
            $content_value[] = ":$keyName";
            $content_field[] = $codeName;
            $content_value[] = ":$codeName";

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
            } catch (\Exception $ex ){
                $delete = $this->prepare("DELETE FROM $table WHERE $idColumnName=:id");
                $delete->execute([':id' => $insertId]);
                throw new \Exception(__CLASS__ . ": Failed to insert content on table [$contentTable] " . $ex->getMessage(), $ex->getCode());
            }
        }
        
        return $insertId;
    }
    
    public function update(array $record, array $content, array $condition): array|false
    {
        if ($this->hasColumn('updated_at')
            && !isset($record['updated_at'])
        ) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        
        if ($this->hasColumn('updated_by')
            && !isset($record['updated_by'])
            && \getenv('CODESAUR_ACCOUNT_ID', true)
        ) {
            $record['updated_by'] = \getenv('CODESAUR_ACCOUNT_ID', true);
        }
        
        $table = $this->getName();
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $is_int_index = $idColumn->isInt();
        $selection = "p.$idColumnName as $idColumnName";
        if (!empty($record)) {
            $set = [];
            foreach (\array_keys($record) as $name) {
                $set[] = "$name=:$name";
                if ($name != $idColumnName) {
                    $selection .= ", p.$name as $name";
                }
            }
            $sets = \implode(', ', $set);
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
        
        $ids = [];
        $select = $this->selectFrom("$table p", $selection, $condition);
        while ($row = $select->fetch(\PDO::FETCH_ASSOC)) {
            $p_id = $is_int_index ? (int) $row[$idColumnName] : $row[$idColumnName];
            if (!isset($ids[$p_id])) {
                if (isset($update)) {
                    foreach ($record as $name => $value) {
                        $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
                    }

                    $update->bindValue(":old_$idColumnName", $p_id, $idColumn->getDataType());
                    if (!$update->execute()) {
                        $error_info = $update->errorInfo();
                        if (\is_numeric($error_info[1] ?? null)) {
                            $error_code = (int) $error_info[1];
                        } elseif (\is_numeric($update->errorCode())) {
                            $error_code = (int) $update->errorCode();
                        } else {
                            $error_code = 0;
                        }
                        throw new \Exception(
                            __CLASS__ . ": Error while updating record on table [$table:$p_id]! " . \implode(': ', $error_info),
                            $error_code
                        );
                    }
                }
                
                $newId = $record[$idColumnName] ?? $p_id;
                
                $contentIds = [];
                foreach ($content as $code => $value) {
                    foreach (\array_keys($value) as $key) {
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
                    $content_row = $content_select->fetch(\PDO::FETCH_ASSOC);
                    if (isset($content_row['id'])) {
                        $content_set = [];
                        foreach (\array_keys($value) as $n) {
                            $content_set[] = "$n=:$n";
                        }
                        $content_sets = \implode(', ', $content_set);
                        $content_stmt = $this->prepare("UPDATE $contentTable SET $content_sets WHERE id=:id");
                        $content_stmt->bindValue(':id', $content_row['id']);
                    } else {
                        $content_col = [];
                        $content_bind = [];
                        foreach (\array_keys($value) as $n) {
                            $content_col[] = $n;
                            $content_bind[] = ":$n";
                        }
                        $content_cols = \implode(', ', $content_col) . ", $keyName, $codeName";
                        $content_binds = \implode(', ', $content_bind) . ", :$keyName, :$codeName";
                        $content_stmt = $this->prepare("INSERT INTO $contentTable($content_cols) VALUES($content_binds)");
                        $content_stmt->bindValue(":$keyName", $p_id, $keyColumn->getDataType());
                        $content_stmt->bindValue(":$codeName", $code, $codeColumn->getDataType());
                    }
                    
                    foreach ($value as $key => $value) {
                        $content_stmt->bindValue(":$key", $value, $this->getContentColumn($key)->getDataType());
                    }
                    
                    try {
                        if (!$content_stmt->execute()) {
                            $error_info = $content_stmt->errorInfo();
                            if (\is_numeric($error_info[1] ?? null)) {
                                $error_code = (int) $error_info[1];
                            } elseif (\is_numeric($update->errorCode())) {
                                $error_code = (int) $update->errorCode();
                            } else {
                                $error_code = 0;
                            }
                            throw new \Exception(\implode(': ', $content_stmt->errorInfo()), $error_code);
                        }
                    } catch (\Exception $ex) {
                        if (isset($update)) {
                            $update->bindValue(":old_$idColumnName", $newId, $idColumn->getDataType());
                            foreach (\array_keys($record) as $name) {
                                $update->bindValue(":$name", $row[$name], $this->getColumn($name)->getDataType());
                            }
                            $update->execute();
                        }
                        throw new \Exception(__CLASS__ . ": Failed to update content on table [$contentTable]! " . $ex->getMessage(), $ex->getCode());
                    }
                    
                    $contentIds[] = (int) ($content_row['id'] ?? $this->lastInsertId());
                }
                
                $ids[$p_id] = [$newId => $contentIds];
            }
        }
        
        return empty($ids) ? false : $ids;
    }
    
    public function updateById(int|string $id, array $record, array $content): array|false
    {
        $idColumnName = $this->getIdColumn()->getName();
        $condition = [
            'WHERE' => "p.$idColumnName=:p_$idColumnName",
            'PARAM' => [":p_$idColumnName" => $id]
        ];
        return $this->update($record, $content, $condition);
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
        $idName = $this->getIdColumn()->getName();
        $keyName = $this->getKeyColumn()->getName();
        $condition['INNER JOIN'] = "$contentTable c ON p.$idName=c.$keyName";
        return $this->selectFrom("$table p", $selection, $condition);
    }

    public function getRows(array $condition = []): array
    {
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $is_int_index = $idColumn->isInt();
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
        $content_KeyColumns = ['id', $this->getKeyColumn()->getName(), $codeName];
        
        $rows = [];
        $pdostmt = $this->select('*', $condition);
        while ($data = $pdostmt->fetch(\PDO::FETCH_ASSOC)) {
            $p_id = $is_int_index ? (int) $data[$p_idName] : $data[$p_idName];
            if (!isset($rows[$p_id][$p_idName])) {
                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();
                    if (isset($data["p_$columnName"])) {
                        if ($column->isInt()) {
                            $value = (int) $data["p_$columnName"];
                        } elseif ($column->isDecimal()) {
                            $value = (float) $data["p_$columnName"];
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
                if (!\in_array($ccolumnName, $content_KeyColumns)) {
                    if (isset($data["c_$ccolumnName"])) {
                        if ($ccolumn->isInt()) {
                            $value = (int) $data["c_$ccolumnName"];
                        } elseif ($ccolumn->isDecimal()) {
                            $value = (float) $data["c_$ccolumnName"];
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
    
    public function getRowBy(array $with_values): array|null
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
        
        if (!empty($wheres)) {
            $condition = [
                'WHERE' => $clause,
                'PARAM' => $params
            ];
            $stmt = $this->select('*', $condition);
            
            $idName = $this->getIdColumn()->getName();
            $codeName = $this->getCodeColumn()->getName();
            $c_codeName = "c_$codeName";
            $content_KeyColumns = ['id', $this->getKeyColumn()->getName(), $codeName];
            
            $row = [];
            while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (!isset($row[$idName])) {
                    foreach ($this->getColumns() as $column) {
                        $columnName = $column->getName();
                        if (isset($data["p_$columnName"])) {
                            if ($column->isInt()) {
                                $value = (int) $data["p_$columnName"];
                            } elseif ($column->isDecimal()) {
                                $value = (float) $data["p_$columnName"];
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
                    if (!\in_array($ccolumnName, $content_KeyColumns)) {
                        if (isset($data["c_$ccolumnName"])) {
                            if ($ccolumn->isInt()) {
                                $value = (int) $data["c_$ccolumnName"];
                            } elseif ($ccolumn->isDecimal()) {
                                $value = (float) $data["c_$ccolumnName"];
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
    
    public function getById(int|string $id, ?string $code = null): array|null
    {
        $with_values = [
            'p.' . $this->getIdColumn()->getName() => $id
        ];
        if ($this->hasColumn('is_active')
            && $this->getColumn('is_active')->isInt()
        ) {
            $with_values['p.is_active'] = 1;
        }
        if (!empty($code)) {
            $with_values['c.' . $this->getCodeColumn()->getName()] = $code;
        }
        return $this->getRowBy($with_values);
    }
}
