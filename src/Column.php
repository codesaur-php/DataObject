<?php

namespace codesaur\DataObject;

/**
 * Class Column
 *
 * SQL хүснэгтийн нэг баганын бүтцийг тодорхойлох зориулалттай класс.
 * 
 * Энэ нь хүснэгт үүсгэх үеийн:
 *  - баганын нэр
 *  - өгөгдлийн төрөл
 *  - урт/хэмжээ
 *  - NULL эсэх
 *  - анхдагч утга
 *  - PRIMARY KEY, UNIQUE эсэх
 *  - AUTO INCREMENT эсэх
 * зэрэг бүх тохиргоог агуулна.
 *
 * @package codesaur\DataObject
 */
class Column
{
    /** @var string Баганын нэр */
    private readonly string $_name;

    /** @var string Баганын төрөл (varchar, int, date…) */
    private readonly string $_type;

    /** @var int|string|null Төрлийн урт, хэмжээ (VARCHAR(255) гэх мэт) */
    private readonly int|string|null $_length;

    /** @var bool NULL зөвшөөрөх эсэх */
    private readonly bool $_is_null;

    /** @var bool AUTO_INCREMENT эсэх */
    private bool $_is_auto;

    /** @var bool UNIQUE багана эсэх */
    private readonly bool $_is_unique;

    /** @var bool PRIMARY KEY эсэх */
    private readonly bool $_is_primary;

    /** @var string|int|float|bool|null Анхдагч утга */
    private string|int|float|bool|null $_default = null;

    /**
     * Column constructor.
     *
     * @param string $name  Баганын нэр
     * @param string $type  Өгөгдлийн төрөл
     * @param int|string|null $length  Төрлийн урт/хэмжээ
     */
    public function __construct(
        string $name,
        string $type,
        int|string|null $length = null
    ) {
        $this->_name = $name;
        $this->_type = \strtolower($type);
        $this->_length = $length;
    }

    /**
     * Анхдагч утга тохируулах.
     *
     * @param string|int|float|bool|null $default
     * @return $this
     */
    public function default(string|int|float|bool|null $default)
    {
        $this->_default = $default;
        return $this;
    }

    /**
     * AUTO_INCREMENT тохируулах.
     *
     * @param bool $auto
     * @return $this
     */
    public function auto(bool $auto = true): Column
    {
        $this->_is_auto = $auto;
        return $this;
    }

    /**
     * UNIQUE багана болгох.
     *
     * @param bool $unique
     * @return $this
     */
    public function unique(bool $unique = true): Column
    {
        $this->_is_unique = $unique;
        return $this;
    }

    /**
     * PRIMARY KEY болгох.
     *
     * @param bool $primary
     * @return $this
     */
    public function primary(bool $primary = true): Column
    {
        $this->_is_primary = $primary;
        return $this;
    }

    /**
     * NOT NULL тохируулах.
     *
     * @param bool $not_null
     * @return $this
     */
    public function notNull(bool $not_null = true): Column
    {
        $this->_is_null = !$not_null;
        return $this;
    }

    /** Баганын нэр авах. */
    public function getName(): string
    {
        return $this->_name;
    }

    /** Баганын өгөгдлийн төрөл авах. */
    public function getType(): string
    {
        return $this->_type;
    }

    /**
     * PDO-д ашиглагдах өгөгдлийн төрөл тодорхойлох.
     * @return int PDO::PARAM_*
     */
    public function getDataType(): int
    {
        return $this->isInt() ? \PDO::PARAM_INT : \PDO::PARAM_STR;
    }

    /** Төрлийн урт авах. */
    public function getLength(): int|string|null
    {
        return $this->_length;
    }

    /** Анхдагч утга авах. */
    public function getDefault(): string|int|float|bool|null
    {
        return $this->_default;
    }

    /** AUTO_INCREMENT эсэх. */
    public function isAuto(): bool
    {
        return $this->_is_auto ?? false;
    }

    /** Текстэн төрөл эсэх. */
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

    /** Бүх боломжит integer төрлүүд. */
    public function isInt(): bool
    {
        return $this->getType() == 'int'
            || $this->getType() == 'bigint'
            || $this->getType() == 'integer'
            || $this->getType() == 'smallint'
            || $this->getType() == 'int8'
            || $this->getType() == 'bigserial'
            || $this->getType() == 'serial'
            || $this->getType() == 'tinyint'
            || $this->getType() == 'mediumint'
            || $this->getType() == 'bool'
            || $this->getType() == 'boolean';
    }

    /** Аравтын тоонууд. */
    public function isDecimal(): bool
    {
        return $this->getType() == 'decimal'
            || $this->getType() == 'numeric'
            || $this->getType() == 'float'
            || $this->getType() == 'double'
            || $this->getType() == 'real';
    }

    /** Огноо/цаг төрлүүд. */
    public function isDateTime(): bool
    {
        return $this->getType() == 'datetime'
            || $this->getType() == 'date'
            || $this->getType() == 'timestamp'
            || $this->getType() == 'time'
            || $this->getType() == 'timestamptz'
            || $this->getType() == 'year';
    }

    /** BIT төрөл эсэх. */
    public function isBit(): bool
    {
        return $this->getType() == 'bit';
    }

    /** Тоон утгууд уу? */
    public function isNumeric(): bool
    {
        return $this->isInt()
            || $this->isDecimal()
            || $this->isBit();
    }

    /** NULL зөвшөөрөх эсэх. */
    public function isNull(): bool
    {
        return $this->_is_null ?? true;
    }

    /** PRIMARY KEY эсэх. */
    public function isPrimary(): bool
    {
        return $this->_is_primary ?? false;
    }

    /** UNIQUE эсэх. */
    public function isUnique(): bool
    {
        return $this->_is_unique ?? false;
    }
}
