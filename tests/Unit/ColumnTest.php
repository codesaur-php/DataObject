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

    public function testColumnWithDefault(): void
    {
        $column = (new Column('status', 'tinyint'))->default(1);

        $this->assertEquals(1, $column->getDefault());
    }

    public function testColumnPrimary(): void
    {
        $column = (new Column('id', 'bigint'))->primary();

        $this->assertTrue($column->isPrimary());
        // Primary key нь автоматаар NOT NULL болдог
        // Гэхдээ Column класс дээр primary() дуудахад notNull() автоматаар дуудагдахгүй
        // TableTrait дээр getSyntax() дуудахад notNull() дуудагдана
        $this->assertTrue($column->isPrimary());
    }

    public function testColumnUnique(): void
    {
        $column = (new Column('email', 'varchar', 255))->unique();

        $this->assertTrue($column->isUnique());
    }

    public function testColumnAutoIncrement(): void
    {
        $column = (new Column('id', 'int'))->auto();

        $this->assertTrue($column->isAuto());
    }

    public function testColumnNotNull(): void
    {
        $column = (new Column('name', 'varchar', 255))->notNull();

        $this->assertFalse($column->isNull());
    }

    public function testIsString(): void
    {
        $varchar = new Column('name', 'varchar', 255);
        $text = new Column('body', 'text');
        $int = new Column('id', 'int');

        $this->assertTrue($varchar->isString());
        $this->assertTrue($text->isString());
        $this->assertFalse($int->isString());
    }

    public function testIsInt(): void
    {
        $int = new Column('id', 'int');
        $bigint = new Column('id', 'bigint');
        $varchar = new Column('name', 'varchar', 255);

        $this->assertTrue($int->isInt());
        $this->assertTrue($bigint->isInt());
        $this->assertFalse($varchar->isInt());
    }

    public function testIsDecimal(): void
    {
        $decimal = new Column('price', 'decimal', '10,2');
        $float = new Column('rate', 'float');
        $int = new Column('id', 'int');

        $this->assertTrue($decimal->isDecimal());
        $this->assertTrue($float->isDecimal());
        $this->assertFalse($int->isDecimal());
    }

    public function testIsDateTime(): void
    {
        $datetime = new Column('created_at', 'datetime');
        $date = new Column('birthday', 'date');
        $varchar = new Column('name', 'varchar', 255);

        $this->assertTrue($datetime->isDateTime());
        $this->assertTrue($date->isDateTime());
        $this->assertFalse($varchar->isDateTime());
    }

    public function testIsNumeric(): void
    {
        $int = new Column('id', 'int');
        $decimal = new Column('price', 'decimal', '10,2');
        $varchar = new Column('name', 'varchar', 255);

        $this->assertTrue($int->isNumeric());
        $this->assertTrue($decimal->isNumeric());
        $this->assertFalse($varchar->isNumeric());
    }

    public function testGetDataType(): void
    {
        $int = new Column('id', 'int');
        $varchar = new Column('name', 'varchar', 255);

        $this->assertEquals(\PDO::PARAM_INT, $int->getDataType());
        $this->assertEquals(\PDO::PARAM_STR, $varchar->getDataType());
    }
}
