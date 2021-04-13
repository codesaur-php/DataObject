<?php

namespace codesaur\DataObject;

use PDO;

use Exception;

abstract class Table
{
    use TableTrait;
    
    protected $pdo;                // PHP Data Object
    
    protected $name;              // Table name
    protected $columns = array(); // Table columns
    
    function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    function __initial()
    {
    }
    
    public function getName(): string
    {
        if (empty($this->name)) {
            throw new Exception(__CLASS__ . ': Table name must be provided!');
        }
        
        return $this->name;
    }
    
    public function getVersionName(): string
    {
        return $this->getName() . '_version';
    }
    
    public function getColumns(): array
    {
        return $this->columns;
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

        $this->columns = $columnSets;
    }
    
    public function getColumn(string $name): Column
    {
        if ($this->hasColumn($name)) {
            return $this->columns[$name];
        }
        
        throw new Exception(__CLASS__ . ": Table [{$this->getName()}] definition doesn't have column named [$name]!");
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }
    
    public function getIdColumn(): Column
    {
        return $this->getColumn('id');
    }
    
    public function delete(array $condition)
    {
        $ids = array();
        $table = $this->getName();
        $idColumn = $this->getIdColumn();
        $idColumnName = $idColumn->getName();

        if (getenv('CODESAUR_DB_KEEP_DELETE', true) == 'true'
                && $this->hasColumn('is_active')
        ) {
            $selection = "$idColumnName, is_active";
        
            $uniques = array();
            $set = array('is_active=:is_active');
            foreach ($this->getColumns() as $column) {
                $uniqueName = $column->getName();
                if ($column->isUnique()
                        && $uniqueName != $idColumnName
                ) {
                    $uniques[] = $column;
                    $selection .= ", $uniqueName";
                    $set[] = "$uniqueName=:$uniqueName";
                }
            }
            $sets = implode(', ', $set);
            $update = $this->prepare("UPDATE $table SET $sets WHERE $idColumnName=:$idColumnName");
            $select = $this->selectStatement($table, $selection, $condition);
            while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
                if (!$row['is_active']) {
                    continue;
                }

                $update->bindValue(":$idColumnName", $row[$idColumnName], $idColumn->getDataType());
                $update->bindValue(':is_active', 0, PDO::PARAM_INT);
                foreach ($uniques as $unique) {
                    $uniqueName = $unique->getName();
                    if ($unique->isNumeric()) {
                        $row[$uniqueName] = PHP_INT_MAX - $row[$uniqueName];
                    } else {
                        $row[$uniqueName] = '[' . uniqid() . '] ' . $row[$uniqueName];
                    }

                    $update->bindValue(":$uniqueName", $row[$uniqueName], $unique->getDataType());
                }

                if ($update->execute()) {
                    $ids[] = $idColumn->isInt() ? (int)$row[$idColumnName] : $row[$idColumnName];
                }
            }
        } else {
            $select = $this->selectStatement($table, $idColumnName, $condition);
            $delete = $this->prepare("DELETE FROM $table WHERE $idColumnName=:id");
            while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
                $delete->bindValue(':id', $row[$idColumnName]);
                $delete_executed = $delete->execute();
                if ($delete->rowCount()
                        || ($delete_executed && $this->driverName() !== 'mysql')
                ) {
                    $ids[] = $idColumn->isInt() ? (int)$row[$idColumnName] : $row[$idColumnName];
                }
            }
        }

        return empty($ids) ? false : $ids;
    }
    
    public function deleteById($id)
    {
        $idColumn = $this->getIdColumn();
        $condition = array(
            'WHERE' => $idColumn->getName() . '=:id',
            'PARAM' => array(':id' => ['value' => $id, 'data_type' => $idColumn->getDataType()])
        );
        
        return $this->delete($condition);
    }
}
