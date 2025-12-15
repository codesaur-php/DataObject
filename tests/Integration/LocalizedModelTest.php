<?php

namespace codesaur\DataObject\Tests\Integration;

use PHPUnit\Framework\TestCase;

use PDO;

use codesaur\DataObject\LocalizedModel;
use codesaur\DataObject\Column;

class TestLocalizedModel extends LocalizedModel
{
    public function __construct(PDO $pdo)
    {
        $this->setInstance($pdo);
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('slug', 'varchar', 128),
           (new Column('is_active', 'tinyint'))->default(1)
        ]);
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('description', 'text')
        ]);
        $this->setTable('test_localized');
    }

    protected function __initial()
    {
        // Test initial
    }
}

class LocalizedModelTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?TestLocalizedModel $model = null;

    protected function setUp(): void
    {
        // SQLite in-memory database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // SQLite дээр FK-г идэвхжүүлэх
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        
        $this->model = new TestLocalizedModel($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->model = null;
        $this->pdo = null;
    }

    public function testLocalizedInsert(): void
    {
        $result = $this->model->insert(
            ['slug' => 'test-article'],
            [
                'en' => [
                    'title' => 'English Title',
                    'description' => 'English Description'
                ],
                'mn' => [
                    'title' => 'Монгол Гарчиг',
                    'description' => 'Монгол Тайлбар'
                ]
            ]
        );

        $this->assertIsArray($result);
        $this->assertEquals('test-article', $result['slug']);
        $this->assertArrayHasKey('localized', $result);
        $this->assertArrayHasKey('en', $result['localized']);
        $this->assertArrayHasKey('mn', $result['localized']);
        $this->assertEquals('English Title', $result['localized']['en']['title']);
        $this->assertEquals('Монгол Гарчиг', $result['localized']['mn']['title']);
    }

    public function testGetRow(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'test'],
            [
                'en' => ['title' => 'Test EN'],
                'mn' => ['title' => 'Test MN']
            ]
        );
        $id = $inserted['id'];

        $row = $this->model->getRowWhere(['p.id' => $id]);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('localized', $row);
        $this->assertArrayHasKey('en', $row['localized']);
        $this->assertArrayHasKey('mn', $row['localized']);
    }

    public function testGetRowByCode(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'test'],
            [
                'en' => ['title' => 'English Title'],
                'mn' => ['title' => 'Монгол Гарчиг']
            ]
        );
        $id = $inserted['id'];

        $row = $this->model->getRowByCode($id, 'en');

        $this->assertIsArray($row);
        $this->assertArrayHasKey('localized', $row);
        $this->assertArrayHasKey('title', $row['localized']);
        $this->assertEquals('English Title', $row['localized']['title']);
        // Should not have language code level
        $this->assertArrayNotHasKey('en', $row['localized']);
    }

    public function testUpdateById(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'original'],
            ['en' => ['title' => 'Original']]
        );
        $id = $inserted['id'];

        $updated = $this->model->updateById(
            $id,
            ['slug' => 'updated'],
            ['en' => ['title' => 'Updated']]
        );

        $this->assertEquals('updated', $updated['slug']);
        $this->assertEquals('Updated', $updated['localized']['en']['title']);
    }

    public function testGetRows(): void
    {
        $this->model->insert(
            ['slug' => 'article-1'],
            ['en' => ['title' => 'Article 1']]
        );
        $this->model->insert(
            ['slug' => 'article-2'],
            ['en' => ['title' => 'Article 2']]
        );

        $rows = $this->model->getRows();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('localized', $row);
            $this->assertArrayHasKey('en', $row['localized']);
        }
    }
}
