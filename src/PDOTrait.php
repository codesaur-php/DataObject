<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

trait PDOTrait
{   
    function __destruct()
    {
        if ($this->pdo instanceof PDO) {
            $this->pdo = null;
        }
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
    
    public function hasTable(string $name): bool
    {
        return $this->query('SHOW TABLES LIKE ' .  $this->quote($name))->rowCount() > 0;
    }
    
    public function setForeignKeyChecks(bool $enable = true)
    {
        $this->exec('set foreign_key_checks=' . ($enable ? 1 : 0));
    }
}
