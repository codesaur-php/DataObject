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
    
    private ?string $_driver;
    
    public final function setInstance(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public final function getDriverName()
    {
        if (empty($this->_driver)) {
           $this->_driver = \strtolower($this->pdo?->getAttribute(PDO::ATTR_DRIVER_NAME));
        }
        
        return $this->_driver;
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
    
    public final function hasTable(string $table): bool
    {
        switch ($this->getDriverName()) {
            case 'mysql':
                return $this->query('SHOW TABLES LIKE ' . $this->quote($table))->rowCount() > 0;
            case 'pgsql':
                return $this->query("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename=" . $this->quote($table))->rowCount() > 0;
            default:
                throw new \RuntimeException('doesn\'t support a driver');
        }
    }
    
    public final function setForeignKeyChecks(bool $enable): int|false
    {
        switch ($this->getDriverName()) {
            case 'mysql':
                return $this->exec('SET foreign_key_checks=' . ($enable ? 1 : 0));
            case 'pgsql':
                return $this->exec('SET session_replication_role = ' . $this->quote($enable ? 'origin' : 'replica'));
            default:
                throw new \RuntimeException('doesn\'t support a driver');
        }
    }
}
