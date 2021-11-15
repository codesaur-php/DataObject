<?php

namespace codesaur\DataObject;

use PDO;

use Exception;

trait TableTrait
{
    use StatementTrait;
    
    /**
     * The sql table name.
     *
     * @var string|null
     */
    protected $name;
    
    /**
     * The sql table columns definitions.
     *
     * @var array
     */
    protected $columns = array();
    
    function __initial()
    {
    }
    
    public function getName(): string
    {
        if (empty($this->name)) {
            throw new Exception(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        return $this->name;
    }
    
    public function setTable(string $name, $collate = null)
    {
        $this->name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        $table = $this->getName();
        $columns = $this->getColumns();
        if (empty($columns)) {
            throw new Exception(__CLASS__ . ": Must define columns before table [$table] set", 1113);
        } elseif ($this->hasTable($table)) {
            return;
        }
        
        $this->createTable($table, $columns, $collate);
        $this->__initial();
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
                throw new Exception(__CLASS__ . ': Column should have been instance of Column class');
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
        
        throw new Exception(__CLASS__ . ": Table [{$this->getName()}] definition doesn't have column named [$name]", 1054);
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

        if ($this->hasColumn('is_active')
                && getenv('CODESAUR_DELETE_DEACTIVATE', true) == 'true'
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
            $select = $this->selectFrom($table, $selection, $condition);
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
            $select = $this->selectFrom($table, $idColumnName, $condition);
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
        $idColumnName = $this->getIdColumn()->getName();
        $condition = array(
            'WHERE' => "$idColumnName=:id",
            'PARAM' => array(':id' => $id)
        );        
        return $this->delete($condition);
    }
}
