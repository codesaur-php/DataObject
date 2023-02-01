<?php

namespace codesaur\DataObject;

abstract class Model
{
    use TableTrait;
    
    public function insert(array $record): int|string|false
    {
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
        $params = \implode(', ', $param);
        
        $table = $this->getName();
        $insert = $this->prepare("INSERT INTO $table($columns) VALUES($params)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        if (!$insert->execute()) {
            return false;
        }
        
        $idColumn = $this->getIdColumn();
        $insertId = $record[$idColumn->getName()] ?? $this->lastInsertId();
        return $idColumn->isInt() ? (int) $insertId : $insertId;
    }
    
    public function update(array $record, array $condition): array|false
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
        
        $set = [];
        foreach (\array_keys($record) as $name) {
            $set[] = "$name=:$name";
        }
        $sets = \implode(', ', $set);
        
        $ids = [];
        $table = $this->getName();
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $is_int_index = $idColumn->isInt();
        $select = $this->select($idColumnName, $condition);
        $update = $this->prepare("UPDATE $table SET $sets WHERE $idColumnName=:old_$idColumnName");
        while ($row = $select->fetch(\PDO::FETCH_ASSOC)) {
            $update->bindValue(":old_$idColumnName", $row[$idColumnName]);
            
            foreach ($record as $name => $value) {
                $update->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
            }

            if ($update->execute()) {
                $oldId = $row[$idColumnName];
                $newId = $record[$idColumnName] ?? $oldId;
                if ($is_int_index) {
                    $ids[(int) $oldId] = (int) $newId;
                } else {
                    $ids[$oldId] = $newId;
                }
            }
        }
        
        return empty($ids) ? false : $ids;
    }
    
    public function updateById(int|string $id, array $record): array|false
    {
        $idColumnName = $this->getIdColumn()->getName();
        $condition = [
            'WHERE' => "$idColumnName=:id",
            'PARAM' => [':id' => $id]
        ];
        return $this->update($record, $condition);
    }
    
    public function select(string $selection = '*', array $condition = []): \PDOStatement
    {
        return $this->selectFrom($this->getName(), $selection, $condition);
    }
    
    public function getRows(array $condition = []): array
    {
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();
        $is_int_index = $idColumn->isInt();
        
        if (empty($condition)) {
            $condition['ORDER BY'] = $idColumnName;
            
            if ($this->hasColumn('is_active')
                && $this->getColumn('is_active')->isInt()
            ) {
                $condition['WHERE'] = 'is_active=1';
            }
        }
        
        $rows = [];
        $stmt = $this->select('*', $condition);
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($is_int_index) {
                $id = (int) $data[$idColumnName];
            } else {
                $id = $data[$idColumnName];
            }
            foreach ($this->getColumns() as $column) {
                if (isset($data[$column->getName()])) {
                    if ($column->isInt()) {
                        $value = (int) $data[$column->getName()];
                    } elseif ($column->isDecimal()) {
                        $value = (float) $data[$column->getName()];
                    } else {
                        $value = $data[$column->getName()];
                    }
                } else {
                    $value = $column->getDefault();
                }
                
                $rows[$id][$column->getName()] = $value;
            }
        }
        
        return $rows;
    }
    
    public function getRowBy(array $with_values): array|null
    {
        $where = [];
        $params = [];
        foreach ($with_values as $key => $value) {
            $where[] = "$key=:$key";
            $params[":$key"] = $value;
        }
        $clause = \implode(' AND ', $where);
        
        if (!empty($clause)) {
            $condition = [
                'WHERE' => $clause,
                'LIMIT' => 1,
                'PARAM' => $params
            ];
            $stmt = $this->select('*', $condition);
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                foreach ($this->getColumns() as $column) {
                    if (isset($row[$column->getName()])) {
                        if ($column->isInt()) {
                            $row[$column->getName()] = (int) $row[$column->getName()];
                        } elseif ($column->isDecimal()) {
                            $row[$column->getName()] = (float) $row[$column->getName()];
                        }
                    }
                }
                
                return $row;
            }
        }
        
        return null;
    }
    
    public function getById(int|string $id): array|null
    {
        $with_values = [
            $this->getIdColumn()->getName() => $id
        ];
        if ($this->hasColumn('is_active')
            && $this->getColumn('is_active')->isInt()
        ) {
            $with_values['is_active'] = 1;
        }
        return $this->getRowBy($with_values);
    }
}
