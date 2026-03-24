<?php

namespace codesaur\DataObject\Tests\Unit;

use PHPUnit\Framework\TestCase;

use codesaur\DataObject\Column;

class ColumnTest extends TestCase
{
    public function testColumnCreation(): void
    {
        $column = new Column('name', 'varchar', 255);

        $this->assertEquals('name', $column->getName());
        $this->assertEquals('varchar', $column->getType());
        $this->assertEquals(255, $column->getLength());
    }

    public function testColumnCreationWithoutLength(): void
    {
        $column = new Column('body', 'text');

        $this->assertEquals('body', $column->getName());
        $this->assertEquals('text', $column->getType());
        $this->assertNull($column->getLength());
    }

    public function testColumnTypeLowercased(): void
    {
        $column = new Column('id', 'BIGINT');
        $this->assertEquals('bigint', $column->getType());

        $column2 = new Column('name', 'VarChar', 255);
        $this->assertEquals('varchar', $column2->getType());
    }

    public function testColumnWithDefault(): void
    {
        $column = (new Column('status', 'tinyint'))->default(1);
        $this->assertEquals(1, $column->getDefault());
    }

    public function testColumnDefaultNull(): void
    {
        $column = new Column('name', 'varchar', 255);
        $this->assertNull($column->getDefault());
    }

    public function testColumnWithStringDefault(): void
    {
        $column = (new Column('status', 'varchar', 50))->default('active');
        $this->assertEquals('active', $column->getDefault());
    }

    public function testColumnWithBoolDefault(): void
    {
        $column = (new Column('flag', 'tinyint'))->default(false);
        $this->assertFalse($column->getDefault());
    }

    public function testColumnPrimary(): void
    {
        $column = (new Column('id', 'bigint'))->primary();

        $this->assertTrue($column->isPrimary());
    }

    public function testColumnPrimaryDefaultFalse(): void
    {
        $column = new Column('id', 'bigint');
        $this->assertFalse($column->isPrimary());
    }

    public function testColumnUnique(): void
    {
        $column = (new Column('email', 'varchar', 255))->unique();

        $this->assertTrue($column->isUnique());
    }

    public function testColumnUniqueDefaultFalse(): void
    {
        $column = new Column('email', 'varchar', 255);
        $this->assertFalse($column->isUnique());
    }

    public function testColumnAutoIncrement(): void
    {
        $column = (new Column('id', 'int'))->auto();

        $this->assertTrue($column->isAuto());
    }

    public function testColumnAutoDefaultFalse(): void
    {
        $column = new Column('id', 'int');
        $this->assertFalse($column->isAuto());
    }

    public function testColumnNotNull(): void
    {
        $column = (new Column('name', 'varchar', 255))->notNull();

        $this->assertFalse($column->isNull());
    }

    public function testColumnNullByDefault(): void
    {
        $column = new Column('name', 'varchar', 255);
        $this->assertTrue($column->isNull());
    }

    public function testMethodChaining(): void
    {
        $column = (new Column('id', 'bigint'))
            ->primary()
            ->auto()
            ->notNull()
            ->default(0);

        $this->assertTrue($column->isPrimary());
        $this->assertTrue($column->isAuto());
        $this->assertFalse($column->isNull());
        $this->assertEquals(0, $column->getDefault());
    }

    // --- isString ---

    public function testIsString(): void
    {
        $this->assertTrue((new Column('c', 'varchar', 255))->isString());
        $this->assertTrue((new Column('c', 'text'))->isString());
        $this->assertTrue((new Column('c', 'blob'))->isString());
        $this->assertTrue((new Column('c', 'binary'))->isString());
        $this->assertTrue((new Column('c', 'varbinary'))->isString());
        $this->assertTrue((new Column('c', 'char', 1))->isString());
        $this->assertTrue((new Column('c', 'tinytext'))->isString());
        $this->assertTrue((new Column('c', 'mediumtext'))->isString());
        $this->assertTrue((new Column('c', 'longtext'))->isString());
        $this->assertTrue((new Column('c', 'tinyblob'))->isString());
        $this->assertTrue((new Column('c', 'mediumblob'))->isString());
        $this->assertTrue((new Column('c', 'longblob'))->isString());
        $this->assertTrue((new Column('c', 'enum'))->isString());
        $this->assertTrue((new Column('c', 'set'))->isString());

        $this->assertFalse((new Column('c', 'int'))->isString());
        $this->assertFalse((new Column('c', 'datetime'))->isString());
    }

    // --- isInt ---

    public function testIsInt(): void
    {
        $this->assertTrue((new Column('c', 'int'))->isInt());
        $this->assertTrue((new Column('c', 'bigint'))->isInt());
        $this->assertTrue((new Column('c', 'integer'))->isInt());
        $this->assertTrue((new Column('c', 'smallint'))->isInt());
        $this->assertTrue((new Column('c', 'int8'))->isInt());
        $this->assertTrue((new Column('c', 'bigserial'))->isInt());
        $this->assertTrue((new Column('c', 'serial'))->isInt());
        $this->assertTrue((new Column('c', 'tinyint'))->isInt());
        $this->assertTrue((new Column('c', 'mediumint'))->isInt());
        $this->assertTrue((new Column('c', 'bool'))->isInt());
        $this->assertTrue((new Column('c', 'boolean'))->isInt());

        $this->assertFalse((new Column('c', 'varchar', 255))->isInt());
        $this->assertFalse((new Column('c', 'float'))->isInt());
    }

    // --- isDecimal ---

    public function testIsDecimal(): void
    {
        $this->assertTrue((new Column('c', 'decimal', '10,2'))->isDecimal());
        $this->assertTrue((new Column('c', 'numeric'))->isDecimal());
        $this->assertTrue((new Column('c', 'float'))->isDecimal());
        $this->assertTrue((new Column('c', 'double'))->isDecimal());
        $this->assertTrue((new Column('c', 'real'))->isDecimal());

        $this->assertFalse((new Column('c', 'int'))->isDecimal());
        $this->assertFalse((new Column('c', 'varchar', 50))->isDecimal());
    }

    // --- isDateTime ---

    public function testIsDateTime(): void
    {
        $this->assertTrue((new Column('c', 'datetime'))->isDateTime());
        $this->assertTrue((new Column('c', 'date'))->isDateTime());
        $this->assertTrue((new Column('c', 'timestamp'))->isDateTime());
        $this->assertTrue((new Column('c', 'time'))->isDateTime());
        $this->assertTrue((new Column('c', 'timestamptz'))->isDateTime());
        $this->assertTrue((new Column('c', 'year'))->isDateTime());

        $this->assertFalse((new Column('c', 'varchar', 255))->isDateTime());
        $this->assertFalse((new Column('c', 'int'))->isDateTime());
    }

    // --- isBit ---

    public function testIsBit(): void
    {
        $this->assertTrue((new Column('c', 'bit'))->isBit());
        $this->assertFalse((new Column('c', 'int'))->isBit());
        $this->assertFalse((new Column('c', 'varchar', 255))->isBit());
    }

    // --- isNumeric ---

    public function testIsNumeric(): void
    {
        // int -> numeric
        $this->assertTrue((new Column('c', 'int'))->isNumeric());
        // decimal -> numeric
        $this->assertTrue((new Column('c', 'decimal', '10,2'))->isNumeric());
        // bit -> numeric
        $this->assertTrue((new Column('c', 'bit'))->isNumeric());
        // string -> not numeric
        $this->assertFalse((new Column('c', 'varchar', 255))->isNumeric());
        // datetime -> not numeric
        $this->assertFalse((new Column('c', 'datetime'))->isNumeric());
    }

    // --- getDataType ---

    public function testGetDataType(): void
    {
        $this->assertEquals(\PDO::PARAM_INT, (new Column('c', 'int'))->getDataType());
        $this->assertEquals(\PDO::PARAM_INT, (new Column('c', 'bigint'))->getDataType());
        $this->assertEquals(\PDO::PARAM_INT, (new Column('c', 'tinyint'))->getDataType());
        $this->assertEquals(\PDO::PARAM_INT, (new Column('c', 'boolean'))->getDataType());

        $this->assertEquals(\PDO::PARAM_STR, (new Column('c', 'varchar', 255))->getDataType());
        $this->assertEquals(\PDO::PARAM_STR, (new Column('c', 'text'))->getDataType());
        $this->assertEquals(\PDO::PARAM_STR, (new Column('c', 'decimal', '10,2'))->getDataType());
        $this->assertEquals(\PDO::PARAM_STR, (new Column('c', 'datetime'))->getDataType());
    }

    public function testColumnStringLength(): void
    {
        $column = new Column('price', 'decimal', '10,2');
        $this->assertEquals('10,2', $column->getLength());
    }
}
