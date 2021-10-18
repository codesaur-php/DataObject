<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

use Exception;

trait PDOTrait
{
    /**
     * The PHP Data Object instance.
     *
     * @var PDO|null
     */
    protected $pdo;
    
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
        $stmt =  $this->pdo->prepare($statement, $driver_options);
        
        if ($stmt !== false) {
            return $stmt;
        }
        
        throw new Exception(__CLASS__ . ": PDO error! " .  implode(': ', $this->pdo->errorInfo()),
                is_int($this->pdo->errorInfo()[1] ?? null) ? $this->pdo->errorInfo()[1] : $this->pdo->errorCode());
    }

    public function exec(string $statement)
    {
        return $this->pdo->exec($statement);
    }

    public function query(string $statement): PDOStatement
    {
        $stmt =  $this->pdo->query($statement);
        
        if ($stmt !== false) {
            return $stmt;
        }
        
        throw new Exception(__CLASS__ . ": PDO error! " .  implode(': ', $this->pdo->errorInfo()),
                is_int($this->pdo->errorInfo()[1] ?? null) ? $this->pdo->errorInfo()[1] : $this->pdo->errorCode());
    }

    public function lastInsertId(string $name = NULL): string
    {
        return $this->pdo->lastInsertId($name);
    }
    
    public function hasTable(string $table): bool
    {
        return $this->query('SHOW TABLES LIKE ' .  $this->quote($table))->rowCount() > 0;
    }
    
    public function setForeignKeyChecks(bool $enable)
    {
        $this->exec('set foreign_key_checks=' . ($enable ? 1 : 0));
    }
}
