<?php

namespace codesaur\DataObject\Tests\Unit;

use PHPUnit\Framework\TestCase;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class TableTraitTestModel extends Model
{
    private bool $skipTable;

    public function __construct(PDO $pdo, bool $skipTable = false)
    {
        $this->skipTable = $skipTable;
        $this->setInstance($pdo);
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('name', 'varchar', 255),
            new Column('email', 'varchar', 255),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('score', 'decimal', '10,2'),
            new Column('created_at', 'datetime')
        ]);
        if (!$skipTable) {
            $this->setTable('table_trait_test');
        }
    }

    protected function __initial()
    {
    }
}

class TableTraitTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    public function testGetName(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $this->assertEquals('table_trait_test', $model->getName());
    }

    public function testGetColumns(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $columns = $model->getColumns();

        $this->assertIsArray($columns);
        $this->assertCount(6, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertArrayHasKey('is_active', $columns);
    }

    public function testHasColumn(): void
    {
        $model = new TableTraitTestModel($this->pdo);

        $this->assertTrue($model->hasColumn('id'));
        $this->assertTrue($model->hasColumn('name'));
        $this->assertFalse($model->hasColumn('nonexistent'));
    }

    public function testGetColumn(): void
    {
        $model = new TableTraitTestModel($this->pdo);

        $column = $model->getColumn('id');
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals('id', $column->getName());
        $this->assertTrue($column->isPrimary());
    }

    public function testGetColumnThrowsOnInvalidName(): void
    {
        $model = new TableTraitTestModel($this->pdo);

        $this->expectException(\Exception::class);
        $model->getColumn('nonexistent');
    }

    public function testSetColumnsRejectsNonColumnObjects(): void
    {
        $this->expectException(\Exception::class);

        $model = new TableTraitTestModel($this->pdo, true);
        $model->setColumns(['not_a_column_object']);
    }

    public function testDeleteById(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $inserted = $model->insert(['name' => 'ToDelete', 'email' => 'del@test.com']);
        $id = $inserted['id'];

        $result = $model->deleteById($id);
        $this->assertTrue($result);

        $this->assertFalse($model->existsById($id));
    }

    public function testDeleteByIdNonExistent(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $result = $model->deleteById(99999);
        $this->assertFalse($result);
    }

    public function testDeactivateById(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $inserted = $model->insert(['name' => 'Active', 'email' => 'active@test.com', 'is_active' => 1]);
        $id = $inserted['id'];

        $result = $model->deactivateById($id);
        $this->assertTrue($result);

        $row = $model->getById($id);
        $this->assertEquals(0, $row['is_active']);
    }

    public function testDeactivateByIdWithExtraRecord(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $inserted = $model->insert(['name' => 'Active2', 'email' => 'active2@test.com', 'is_active' => 1]);
        $id = $inserted['id'];

        $result = $model->deactivateById($id, ['name' => 'Deactivated']);
        $this->assertTrue($result);

        $row = $model->getById($id);
        $this->assertEquals(0, $row['is_active']);
        $this->assertEquals('Deactivated', $row['name']);
    }

    public function testDeactivateByIdAlreadyInactiveThrows(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $inserted = $model->insert(['name' => 'Inactive', 'email' => 'inactive@test.com', 'is_active' => 0]);
        $id = $inserted['id'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already inactive');
        $model->deactivateById($id);
    }

    public function testSelectStatementWithConditions(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $model->insert(['name' => 'Alice', 'email' => 'alice@test.com']);
        $model->insert(['name' => 'Bob', 'email' => 'bob@test.com']);
        $model->insert(['name' => 'Charlie', 'email' => 'charlie@test.com']);

        // WHERE
        $stmt = $model->selectStatement('table_trait_test', '*', [
            'WHERE' => 'name=:name',
            'PARAM' => [':name' => 'Bob']
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertEquals('Bob', $rows[0]['name']);
    }

    public function testSelectStatementWithOrderAndLimit(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $model->insert(['name' => 'Alice', 'email' => 'a@test.com']);
        $model->insert(['name' => 'Bob', 'email' => 'b@test.com']);
        $model->insert(['name' => 'Charlie', 'email' => 'c@test.com']);

        $stmt = $model->selectStatement('table_trait_test', '*', [
            'ORDER BY' => 'name ASC',
            'LIMIT' => '2'
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
        $this->assertEquals('Bob', $rows[1]['name']);
    }

    public function testSelectStatementWithOffset(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        $model->insert(['name' => 'A', 'email' => 'a@t.com']);
        $model->insert(['name' => 'B', 'email' => 'b@t.com']);
        $model->insert(['name' => 'C', 'email' => 'c@t.com']);

        $stmt = $model->selectStatement('table_trait_test', '*', [
            'ORDER BY' => 'name ASC',
            'LIMIT' => '1',
            'OFFSET' => '1'
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertEquals('B', $rows[0]['name']);
    }

    public function testTableNameSanitized(): void
    {
        // Special characters removed from table name
        $pdo = $this->pdo;
        $model = new class($pdo) extends Model {
            public function __construct(PDO $pdo)
            {
                $this->setInstance($pdo);
                $this->setColumns([
                   (new Column('id', 'bigint'))->primary(),
                    new Column('val', 'varchar', 50)
                ]);
                $this->setTable('test@table!name');
            }
            protected function __initial() {}
        };

        $this->assertEquals('testtablename', $model->getName());
    }

    public function testTableExistsAfterCreation(): void
    {
        $model = new TableTraitTestModel($this->pdo);
        // Хүснэгт үүссэн эсэхийг шалгах
        $this->assertTrue($model->hasTable('table_trait_test'));
        // Өөр нэртэй хүснэгт байхгүй
        $this->assertFalse($model->hasTable('nonexistent_table'));
    }
}
