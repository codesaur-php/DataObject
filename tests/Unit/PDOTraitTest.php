<?php

namespace codesaur\DataObject\Tests\Unit;

use PHPUnit\Framework\TestCase;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * PDOTrait-ийн функцуудыг шалгах тест.
 * Model нь TableTrait-г ашигладаг, TableTrait нь PDOTrait-г ашигладаг.
 */
class PDOTraitTestModel extends Model
{
    public function __construct(PDO $pdo)
    {
        $this->setInstance($pdo);
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('name', 'varchar', 255)
        ]);
        $this->setTable('pdo_trait_test');
    }

    protected function __initial()
    {
    }
}

class PDOTraitTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?PDOTraitTestModel $model = null;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->model = new PDOTraitTestModel($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->model = null;
        $this->pdo = null;
    }

    public function testGetDriverName(): void
    {
        $this->assertEquals('sqlite', $this->model->getDriverName());
    }

    public function testHasTable(): void
    {
        $this->assertTrue($this->model->hasTable('pdo_trait_test'));
        $this->assertFalse($this->model->hasTable('non_existent_table'));
    }

    public function testQuote(): void
    {
        $result = $this->model->quote("test'value");
        $this->assertIsString($result);
        $this->assertStringContainsString('test', $result);
    }

    public function testExec(): void
    {
        $result = $this->model->exec("CREATE TABLE exec_test (id INTEGER PRIMARY KEY)");
        $this->assertNotFalse($result);
        $this->assertTrue($this->model->hasTable('exec_test'));
    }

    public function testQuery(): void
    {
        $stmt = $this->model->query("SELECT 1 as val");
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $row['val']);
    }

    public function testPrepare(): void
    {
        $stmt = $this->model->prepare("SELECT * FROM pdo_trait_test WHERE id=:id");
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }
}
