<?php

namespace codesaur\DataObject;

use PDO;
use PDOStatement;

trait PDOTrait
{
    /**
     * The PHP Data Object instance.
     *
     * @var PDO
     */
    protected PDO $pdo;
    
    public final function setInstance(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public final function driverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    public final function databaseName(): ?string
    {
        try {
            return (string) $this->query('select database()')->fetchColumn();
        } catch (\Throwable $e) {
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($e->getMessage());
            }
            return null;
        }
    }
    
    public final function quote(string $string, int $parameter_type = PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($string, $parameter_type);
    }

    public final function prepare(string $statement, array $driver_options = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($statement, $driver_options);
        
        if ($stmt != false) {
            return $stmt;
        }
        
        $error_info = $this->pdo->errorInfo();
        if (\is_numeric($error_info[1] ?? null)) {
            $error_code = (int) $error_info[1];
        } elseif (\is_numeric($this->pdo->errorCode())) {
            $error_code = (int) $this->pdo->errorCode();
        } else {
            $error_code = 0;
        }
        throw new \Exception(__CLASS__ . ': PDO error! ' . \implode(': ', $error_info), $error_code);
    }

    public final function exec(string $statement): int|false
    {
        return $this->pdo->exec($statement);
    }

    public final function query(string $statement): PDOStatement
    {
        $stmt = $this->pdo->query($statement);
        
        if ($stmt != false) {
            return $stmt;
        }
        
        $error_info = $this->pdo->errorInfo();
        if (\is_numeric($error_info[1] ?? null)) {
            $error_code = (int) $error_info[1];
        } elseif (\is_numeric($this->pdo->errorCode())) {
            $error_code = (int) $this->pdo->errorCode();
        } else {
            $error_code = 0;
        }
        throw new \Exception(__CLASS__ . ': PDO error! ' . \implode(': ', $error_info), $error_code);
    }

    public final function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }
    
    public final function hasTable(string $table): bool
    {
        return $this->query('SHOW TABLES LIKE ' . $this->quote($table))->rowCount() > 0;
    }
    
    public final function setForeignKeyChecks(bool $enable): int|false
    {
        return $this->exec('set foreign_key_checks=' . ($enable ? 1 : 0));
    }
}
