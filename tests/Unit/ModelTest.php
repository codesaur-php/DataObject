<?php

namespace codesaur\DataObject\Tests\Unit;

use PHPUnit\Framework\TestCase;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class TestModel extends Model
{
    public function __construct(PDO $pdo)
    {
        $this->setInstance($pdo);
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('name', 'varchar', 255),
           (new Column('is_active', 'tinyint'))->default(1)
        ]);
        $this->setTable('test_model');
    }

    protected function __initial()
    {
    }
}

class ModelTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?TestModel $model = null;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->model = new TestModel($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->model = null;
        $this->pdo = null;
    }

    public function testTableCreation(): void
    {
        $this->assertTrue($this->model->hasTable('test_model'));
    }

    public function testInsert(): void
    {
        $result = $this->model->insert([
            'name' => 'Test Name',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('Test Name', $result['name']);
        $this->assertArrayHasKey('id', $result);
        $this->assertIsInt($result['id']);
    }

    public function testInsertReturnsAllColumns(): void
    {
        $result = $this->model->insert(['name' => 'Full']);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('is_active', $result);
    }

    public function testInsertWithDefaultValue(): void
    {
        $result = $this->model->insert(['name' => 'DefaultTest']);

        // is_active-ийн default утга 1 байх ёстой
        $this->assertEquals(1, $result['is_active']);
    }

    public function testInsertMultiple(): void
    {
        $r1 = $this->model->insert(['name' => 'First']);
        $r2 = $this->model->insert(['name' => 'Second']);

        $this->assertNotEquals($r1['id'], $r2['id']);
        $this->assertEquals('First', $r1['name']);
        $this->assertEquals('Second', $r2['name']);
    }

    public function testGetRowWhere(): void
    {
        $this->model->insert(['name' => 'Test']);

        $row = $this->model->getRowWhere(['name' => 'Test']);

        $this->assertIsArray($row);
        $this->assertEquals('Test', $row['name']);
    }

    public function testGetRowWhereNotFound(): void
    {
        $row = $this->model->getRowWhere(['name' => 'NonExistent']);
        $this->assertNull($row);
    }

    public function testUpdateById(): void
    {
        $inserted = $this->model->insert(['name' => 'Original']);
        $this->assertIsArray($inserted);
        $id = $inserted['id'] ?? null;
        $this->assertNotNull($id);

        $updated = $this->model->updateById($id, ['name' => 'Updated']);

        $this->assertIsArray($updated);
        $this->assertEquals('Updated', $updated['name']);
    }

    public function testUpdateByIdWithEmptyRecordThrows(): void
    {
        $inserted = $this->model->insert(['name' => 'Test']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Must provide updated record');
        $this->model->updateById($inserted['id'], []);
    }

    public function testDeleteById(): void
    {
        $inserted = $this->model->insert(['name' => 'To Delete']);
        $this->assertIsArray($inserted);
        $id = $inserted['id'] ?? null;
        $this->assertNotNull($id);

        $deleted = $this->model->deleteById($id);

        $this->assertTrue($deleted);

        $row = $this->model->getRowWhere(['id' => $id]);
        $this->assertNull($row);
    }

    public function testDeleteByIdNonExistent(): void
    {
        $result = $this->model->deleteById(99999);
        $this->assertFalse($result);
    }

    public function testGetRows(): void
    {
        $this->model->insert(['name' => 'Row 1']);
        $this->model->insert(['name' => 'Row 2']);

        $rows = $this->model->getRows();

        $this->assertCount(2, $rows);
    }

    public function testGetRowsEmpty(): void
    {
        $rows = $this->model->getRows();
        $this->assertCount(0, $rows);
    }

    public function testGetRowsIndexedById(): void
    {
        $r1 = $this->model->insert(['name' => 'Row 1']);
        $r2 = $this->model->insert(['name' => 'Row 2']);

        $rows = $this->model->getRows();

        $this->assertArrayHasKey($r1['id'], $rows);
        $this->assertArrayHasKey($r2['id'], $rows);
    }

    public function testGetRowsWithCondition(): void
    {
        $this->model->insert(['name' => 'Active', 'is_active' => 1]);
        $this->model->insert(['name' => 'Inactive', 'is_active' => 0]);

        $rows = $this->model->getRows([
            'WHERE' => 'is_active=:active',
            'PARAM' => [':active' => 1]
        ]);

        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals('Active', $row['name']);
    }

    public function testGetRowsWithOrderBy(): void
    {
        $this->model->insert(['name' => 'Bravo']);
        $this->model->insert(['name' => 'Alpha']);

        $rows = $this->model->getRows([
            'ORDER BY' => 'name ASC'
        ]);

        $names = array_column($rows, 'name');
        $this->assertEquals('Alpha', $names[0]);
        $this->assertEquals('Bravo', $names[1]);
    }

    public function testCountRows(): void
    {
        $this->assertEquals(0, $this->model->countRows());

        $this->model->insert(['name' => 'Row 1']);
        $this->model->insert(['name' => 'Row 2']);
        $this->model->insert(['name' => 'Row 3']);

        $this->assertEquals(3, $this->model->countRows());
    }

    public function testCountRowsWithCondition(): void
    {
        $this->model->insert(['name' => 'Active1', 'is_active' => 1]);
        $this->model->insert(['name' => 'Active2', 'is_active' => 1]);
        $this->model->insert(['name' => 'Inactive', 'is_active' => 0]);

        $count = $this->model->countRows([
            'WHERE' => 'is_active=:active',
            'PARAM' => [':active' => 1]
        ]);

        $this->assertEquals(2, $count);
    }

    public function testExistsById(): void
    {
        $inserted = $this->model->insert(['name' => 'Exists']);

        $this->assertTrue($this->model->existsById($inserted['id']));
        $this->assertFalse($this->model->existsById(99999));
    }

    public function testGetById(): void
    {
        $inserted = $this->model->insert(['name' => 'GetById']);
        $id = $inserted['id'];

        $row = $this->model->getById($id);

        $this->assertIsArray($row);
        $this->assertEquals('GetById', $row['name']);
        $this->assertEquals($id, $row['id']);
    }

    public function testGetByIdNotFound(): void
    {
        $row = $this->model->getById(99999);
        $this->assertNull($row);
    }

    public function testGetRow(): void
    {
        $this->model->insert(['name' => 'OnlyOne']);

        $row = $this->model->getRow([
            'WHERE' => 'name=:name',
            'PARAM' => [':name' => 'OnlyOne']
        ]);

        $this->assertIsArray($row);
        $this->assertEquals('OnlyOne', $row['name']);
    }

    public function testGetRowReturnsNullForMultipleRows(): void
    {
        $this->model->insert(['name' => 'Same']);
        $this->model->insert(['name' => 'Same']);

        $row = $this->model->getRow([
            'WHERE' => 'name=:name',
            'PARAM' => [':name' => 'Same']
        ]);

        // SQLite дээр олон мөр олдвол null буцаана
        $this->assertNull($row);
    }

    public function testGetRowReturnsNullForNoRows(): void
    {
        $row = $this->model->getRow([
            'WHERE' => 'name=:name',
            'PARAM' => [':name' => 'NonExistent']
        ]);

        $this->assertNull($row);
    }

    public function testDeactivateById(): void
    {
        $inserted = $this->model->insert(['name' => 'ToDeactivate', 'is_active' => 1]);
        $id = $inserted['id'];

        $result = $this->model->deactivateById($id);
        $this->assertTrue($result);

        $row = $this->model->getById($id);
        $this->assertEquals(0, $row['is_active']);
    }
}
