<?php

namespace codesaur\DataObject;

use PDO;

class Column
{
    private $_name;
    private $_type;
    private $_length;
    private $_default = null;
    private $_foreignKey = null;

    private $_is_null = true;
    private $_is_auto = false;
    private $_is_unique = false;
    private $_is_primary = false;
    
    function __construct(
            string $name,
            string $type = 'int',
                   $length = 11,
                   $default = null
    ) {
        $this->setName($name);
        $this->setType($type);
        $this->setLength($length);
        $this->setDefault($default);
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
    
    public function foreignKey(string $reference): Column
    {
        $this->_foreignKey = $reference;
        
        return $this;
    }
    
    final public function getName(): string
    {
        return $this->_name;
    }

    final public function setName(string $name)
    {
        $this->_name = $name;
    }

    public function getType(): string
    {
        return $this->_type;
    }

    public function setType(string $type)
    {
        $this->_type = $type;
    }
    
    public function getDataType(): int
    {
        return $this->isInt() ? PDO::PARAM_INT : PDO::PARAM_STR;
    }

    public function getLength()
    {
        if ($this->isUnique()
                && $this->getType() == 'varchar'
        ) {
            return $this->_length - 15;
        }
        
        return $this->_length;
    }

    public function setLength($length)
    {
        if (is_float($length)) {
            $this->_length = array();
            $this->_length['M'] = (int)$length;
            $this->_length['D'] = (int)(($length - $this->_length['M']) * 10);
        } else {
            $this->_length = $length;
        }
    }

    public function getDefault()
    {
        return $this->_default;
    }

    public function setDefault($default)
    {
        $this->_default = $default;
    }
    
    public function isAuto(): bool
    {
        return $this->_is_auto;
    }

    public function isInt(): bool
    {
        return $this->getType() == 'int'
                || $this->getType() == 'tinyint'
                || $this->getType() == 'bigint';
    }

    public function isNumeric(): bool
    {
        return $this->isInt() || $this->getType() == 'decimal';
    }

    public function isNull(): bool
    {
        return $this->_is_null;
    }

    public function isPrimary(): bool
    {
        return $this->_is_primary;
    }

    public function isUnique(): bool
    {
        return $this->_is_unique;
    }
    
    public function getForeignKey()
    {
        return $this->_foreignKey;
    }
    
    public function getSyntax(): string
    {
        $str = "`$this->_name` $this->_type";
        
        if (!in_array($this->_type, array('text', 'datetime'))) {
            if (is_array($this->_length)) {
                if (isset($this->_length['M'])) {
                    $str .= "({$this->_length['M']}";
                    if (isset($this->_length['D'])) {
                        $str .= ",{$this->_length['D']}";
                    }
                    $str .= ')';
                }
            } else {
                $str .= "($this->_length)";
            }
        }
        
        $default = ' DEFAULT ';
        if ($this->_default !== null) {
            if ($this->isNumeric()) {
                $default .= $this->_default;
            } else {
                $default .= "'$this->_default'";
            }
        } else {
            $default .= 'NULL';
        }
        
        if (!$this->isNull()) {
            $str .= ' NOT NULL';
            if ($this->_default !== null) {
                $str .= $default;
            }
        } else {
            $str .= $default;
        }
        
        if ($this->isAuto()) {
            $str .= ' AUTO_INCREMENT';
        }
        
        return $str;
    }
}
