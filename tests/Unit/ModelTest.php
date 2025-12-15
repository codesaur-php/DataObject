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
        // Test initial
    }
}

class ModelTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?TestModel $model = null;

    protected function setUp(): void
    {
        // SQLite in-memory database for testing
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
        // Table should be created automatically
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

    public function testGetRowWhere(): void
    {
        $this->model->insert(['name' => 'Test']);
        
        $row = $this->model->getRowWhere(['name' => 'Test']);
        
        $this->assertIsArray($row);
        $this->assertEquals('Test', $row['name']);
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

    public function testGetRows(): void
    {
        $this->model->insert(['name' => 'Row 1']);
        $this->model->insert(['name' => 'Row 2']);
        
        $rows = $this->model->getRows();
        
        $this->assertCount(2, $rows);
    }
}
