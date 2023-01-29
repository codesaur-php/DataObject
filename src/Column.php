<?php

namespace codesaur\DataObject;

class Column
{
    private readonly string $_name;
    
    private readonly string $_type;
    
    private readonly int|string|null $_length;
    
    private readonly string|int|float|bool|null $_default;

    private readonly bool $_is_null;
    
    private readonly bool $_is_auto;
    
    private readonly bool $_is_unique;
    
    private readonly bool $_is_primary;
    
    public function __construct(
        string $name,
        string $type,
        int|string|null $length = null,
        string|int|float|bool|null $default = null
    ) {
        $this->_name = $name;
        $this->_type = strtolower($type);
        $this->_length = $length;
        $this->_default = $default;
    }
    
    public function auto(bool $auto = true): Column
    {
        $this->_is_auto = $auto;

        return $this;
    }

    public function unique(bool $unique = true): Column
    {
        $this->_is_unique = $unique;

        return $this;
    }

    public function primary(bool $primary = true): Column
    {
        $this->_is_primary = $primary;
        
        return $this;
    }

    public function notNull(bool $not_null = true): Column
    {
        $this->_is_null = !$not_null;
        
        return $this;
    }
    
    public function getName(): string
    {
        return $this->_name;
    }

    public function getType(): string
    {
        return $this->_type;
    }
    
    public function getDataType(): int
    {
        return $this->isInt() ? \PDO::PARAM_INT : \PDO::PARAM_STR;
    }

    public function getLength(): int|string|null
    {
        return $this->_length;
    }

    public function getDefault(): string|int|float|bool|null
    {
        return $this->_default;
    }
    
    public function isAuto(): bool
    {
        return $this->_is_auto ?? false;
    }
    
    public function isString(): bool
    {
        return $this->getType() == 'varchar'
            || $this->getType() == 'text'
            || $this->getType() == 'blob'
            || $this->getType() == 'binary'
            || $this->getType() == 'varbinary'
            || $this->getType() == 'char'
            || $this->getType() == 'tinytext'
            || $this->getType() == 'mediumtext'
            || $this->getType() == 'longtext'
            || $this->getType() == 'tinyblob'
            || $this->getType() == 'mediumblob'
            || $this->getType() == 'longblob'
            || $this->getType() == 'enum'
            || $this->getType() == 'set';
    }
    
    public function isInt(): bool
    {
        return $this->getType() == 'int'
            || $this->getType() == 'bigint'
            || $this->getType() == 'tinyint'
            || $this->getType() == 'smallint'
            || $this->getType() == 'mediumint'
            || $this->getType() == 'integer'
            || $this->getType() == 'bool'
            || $this->getType() == 'boolean';
    }
    
    public function isDecimal(): bool
    {
        return $this->getType() == 'decimal'
            || $this->getType() == 'float'
            || $this->getType() == 'double'
            || $this->getType() == 'real';
    }
    
    public function isDateTime(): bool
    {
        return $this->getType() == 'datetime'
            || $this->getType() == 'date'
            || $this->getType() == 'timestamp' 
            || $this->getType() == 'time'
            || $this->getType() == 'year';
    }
    
    public function isBit(): bool
    {
        return $this->getType() == 'bit';
    }

    public function isNumeric(): bool
    {
        return $this->isInt()            
            || $this->isDecimal()
            || $this->isBit();
    }

    public function isNull(): bool
    {
        return $this->_is_null ?? true;
    }

    public function isPrimary(): bool
    {
        return $this->_is_primary ?? false;
    }

    public function isUnique(): bool
    {
        return $this->_is_unique ?? false;
    }
    
    public function getSyntax(): string
    {
        $str = "{$this->getName()} {$this->getType()}";
        
        $length = $this->getLength();
        if (!empty($length)) {
            $str .= "($length)";
        }
        
        $syntax = ' DEFAULT ';
        $default = $this->getDefault();
        if ($default !== null) {
            if ($this->isNumeric()) {
                $syntax .= $default;
            } else {
                $syntax .= "'$default'";
            }
        } else {
            $syntax .= 'NULL';
        }
        
        if (!$this->isNull()) {
            $str .= ' NOT NULL';
            if ($default !== null) {
                $str .= $syntax;
            }
        } else {
            $str .= $syntax;
        }
        
        if ($this->isAuto()) {
            $str .= ' AUTO_INCREMENT';
        }
        
        return $str;
    }
}
