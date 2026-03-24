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
    }
}

class LocalizedModelTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?TestLocalizedModel $model = null;

    protected function setUp(): void
    {
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

    // --- Table structure ---

    public function testTableCreation(): void
    {
        $this->assertTrue($this->model->hasTable('test_localized'));
        $this->assertTrue($this->model->hasTable('test_localized_content'));
    }

    public function testGetName(): void
    {
        $this->assertEquals('test_localized', $this->model->getName());
    }

    public function testGetContentName(): void
    {
        $this->assertEquals('test_localized_content', $this->model->getContentName());
    }

    public function testGetContentColumns(): void
    {
        $columns = $this->model->getContentColumns();

        $this->assertIsArray($columns);
        // id, parent_id, code + title, description = 5
        $this->assertCount(5, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('parent_id', $columns);
        $this->assertArrayHasKey('code', $columns);
        $this->assertArrayHasKey('title', $columns);
        $this->assertArrayHasKey('description', $columns);
    }

    public function testGetContentColumn(): void
    {
        $column = $this->model->getContentColumn('title');

        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals('title', $column->getName());
    }

    public function testGetContentColumnThrowsOnInvalid(): void
    {
        $this->expectException(\Exception::class);
        $this->model->getContentColumn('nonexistent');
    }

    public function testSetContentColumnsRejectsUniqueColumns(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('forbidden to contain unique column');

        $pdo = $this->pdo;
        new class($pdo) extends LocalizedModel {
            public function __construct(PDO $pdo)
            {
                $this->setInstance($pdo);
                $this->setColumns([
                   (new Column('id', 'bigint'))->primary(),
                    new Column('slug', 'varchar', 128)
                ]);
                $this->setContentColumns([
                   (new Column('title', 'varchar', 255))->unique()
                ]);
                $this->setTable('unique_content_test');
            }
            protected function __initial() {}
        };
    }

    public function testSetContentColumnsRejectsPredefinedNames(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already has predefined column');

        $pdo = $this->pdo;
        new class($pdo) extends LocalizedModel {
            public function __construct(PDO $pdo)
            {
                $this->setInstance($pdo);
                $this->setColumns([
                   (new Column('id', 'bigint'))->primary(),
                    new Column('slug', 'varchar', 128)
                ]);
                $this->setContentColumns([
                    new Column('parent_id', 'bigint')
                ]);
                $this->setTable('predefined_content_test');
            }
            protected function __initial() {}
        };
    }

    // --- INSERT ---

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
                    'title' => 'Монгол гарчиг',
                    'description' => 'Монгол тайлбар'
                ]
            ]
        );

        $this->assertIsArray($result);
        $this->assertEquals('test-article', $result['slug']);
        $this->assertArrayHasKey('localized', $result);
        $this->assertArrayHasKey('en', $result['localized']);
        $this->assertArrayHasKey('mn', $result['localized']);
        $this->assertEquals('English Title', $result['localized']['en']['title']);
        $this->assertEquals('Монгол гарчиг', $result['localized']['mn']['title']);
    }

    public function testInsertWithSingleLanguage(): void
    {
        $result = $this->model->insert(
            ['slug' => 'single-lang'],
            ['en' => ['title' => 'Only English']]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('en', $result['localized']);
        $this->assertArrayNotHasKey('mn', $result['localized']);
    }

    public function testInsertWithEmptyContentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model->insert(['slug' => 'no-content'], []);
    }

    public function testInsertMultipleRecords(): void
    {
        $r1 = $this->model->insert(
            ['slug' => 'article-1'],
            ['en' => ['title' => 'First']]
        );
        $r2 = $this->model->insert(
            ['slug' => 'article-2'],
            ['en' => ['title' => 'Second']]
        );

        $this->assertNotEquals($r1['id'], $r2['id']);
    }

    // --- getRow ---

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

    public function testGetRowNotFound(): void
    {
        $row = $this->model->getRowWhere(['p.id' => 99999]);
        $this->assertNull($row);
    }

    // --- getById ---

    public function testGetById(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'by-id-test'],
            ['en' => ['title' => 'ById Test']]
        );

        $row = $this->model->getById($inserted['id']);

        $this->assertIsArray($row);
        $this->assertEquals('by-id-test', $row['slug']);
        $this->assertEquals('ById Test', $row['localized']['en']['title']);
    }

    public function testGetByIdNotFound(): void
    {
        $row = $this->model->getById(99999);
        $this->assertNull($row);
    }

    // --- existsById ---

    public function testExistsById(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'exists-test'],
            ['en' => ['title' => 'Exists']]
        );

        $this->assertTrue($this->model->existsById($inserted['id']));
        $this->assertFalse($this->model->existsById(99999));
    }

    // --- updateById ---

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

    public function testUpdateByIdExistingLanguage(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'update-lang'],
            [
                'en' => ['title' => 'Original EN', 'description' => 'Desc EN'],
                'mn' => ['title' => 'Original MN', 'description' => 'Desc MN']
            ]
        );
        $id = $inserted['id'];

        // Байгаа хэлний контентыг шинэчлэх
        $updated = $this->model->updateById(
            $id,
            ['slug' => 'updated-lang'],
            ['en' => ['title' => 'Updated EN']]
        );

        $this->assertEquals('updated-lang', $updated['slug']);
        $this->assertEquals('Updated EN', $updated['localized']['en']['title']);
        // MN хэвээрээ байх ёстой
        $this->assertArrayHasKey('mn', $updated['localized']);
    }

    public function testUpdateByIdOnlyContent(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'content-only'],
            ['en' => ['title' => 'Original Title']]
        );
        $id = $inserted['id'];

        $updated = $this->model->updateById(
            $id,
            [],
            ['en' => ['title' => 'New Title']]
        );

        $this->assertEquals('content-only', $updated['slug']);
        $this->assertEquals('New Title', $updated['localized']['en']['title']);
    }

    public function testUpdateByIdNoDataThrows(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'no-data'],
            ['en' => ['title' => 'Test']]
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No data provided');
        $this->model->updateById($inserted['id'], [], []);
    }

    // --- getRows ---

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

    public function testGetRowsEmpty(): void
    {
        $rows = $this->model->getRows();
        $this->assertCount(0, $rows);
    }

    public function testGetRowsIndexedByPrimaryId(): void
    {
        $r1 = $this->model->insert(
            ['slug' => 'r1'],
            ['en' => ['title' => 'R1']]
        );
        $r2 = $this->model->insert(
            ['slug' => 'r2'],
            ['en' => ['title' => 'R2']]
        );

        $rows = $this->model->getRows();

        $this->assertArrayHasKey((int)$r1['id'], $rows);
        $this->assertArrayHasKey((int)$r2['id'], $rows);
    }

    public function testGetRowsWithCondition(): void
    {
        $this->model->insert(
            ['slug' => 'active', 'is_active' => 1],
            ['en' => ['title' => 'Active']]
        );
        $this->model->insert(
            ['slug' => 'inactive', 'is_active' => 0],
            ['en' => ['title' => 'Inactive']]
        );

        $rows = $this->model->getRows([
            'WHERE' => 'p.is_active=:active',
            'PARAM' => [':active' => 1]
        ]);

        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals('active', $row['slug']);
    }

    // --- getRowsByCode ---

    public function testGetRowsByCode(): void
    {
        $this->model->insert(
            ['slug' => 'article-1'],
            [
                'en' => ['title' => 'Article 1 EN', 'description' => 'Description 1 EN'],
                'mn' => ['title' => 'Нийтлэл 1 MN', 'description' => 'Тайлбар 1 MN']
            ]
        );
        $this->model->insert(
            ['slug' => 'article-2'],
            [
                'en' => ['title' => 'Article 2 EN', 'description' => 'Description 2 EN'],
                'mn' => ['title' => 'Нийтлэл 2 MN', 'description' => 'Тайлбар 2 MN']
            ]
        );

        $rows = $this->model->getRowsByCode('en');

        $this->assertCount(2, $rows);
        foreach ($rows as $id => $row) {
            $this->assertIsInt($id);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('slug', $row);
            $this->assertArrayHasKey('localized', $row);
            // Зөвхөн тухайн хэлний контент (хэлний кодын түвшин байхгүй)
            $this->assertArrayHasKey('title', $row['localized']);
            $this->assertArrayHasKey('description', $row['localized']);
            $this->assertArrayNotHasKey('en', $row['localized']);
            $this->assertArrayNotHasKey('mn', $row['localized']);
            $this->assertStringContainsString('EN', $row['localized']['title']);
        }

        $firstRow = reset($rows);
        $this->assertEquals('Article 1 EN', $firstRow['localized']['title']);
        $this->assertEquals('Description 1 EN', $firstRow['localized']['description']);
    }

    public function testGetRowsByCodeMongolian(): void
    {
        $this->model->insert(
            ['slug' => 'mn-article'],
            [
                'en' => ['title' => 'English'],
                'mn' => ['title' => 'Монгол']
            ]
        );

        $rows = $this->model->getRowsByCode('mn');

        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals('Монгол', $row['localized']['title']);
    }

    public function testGetRowsByCodeWithCondition(): void
    {
        $this->model->insert(
            ['slug' => 'active-article', 'is_active' => 1],
            ['en' => ['title' => 'Active Article']]
        );
        $this->model->insert(
            ['slug' => 'inactive-article', 'is_active' => 0],
            ['en' => ['title' => 'Inactive Article']]
        );

        $rows = $this->model->getRowsByCode('en', [
            'WHERE' => 'p.is_active = :active',
            'PARAM' => [':active' => 1]
        ]);

        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals('active-article', $row['slug']);
        $this->assertEquals(1, $row['is_active']);
        $this->assertEquals('Active Article', $row['localized']['title']);
    }

    public function testGetRowsByCodeEmpty(): void
    {
        $rows = $this->model->getRowsByCode('en');
        $this->assertCount(0, $rows);
    }

    // --- countRows ---

    public function testCountRows(): void
    {
        $this->assertEquals(0, $this->model->countRows());

        $this->model->insert(
            ['slug' => 'count-1'],
            ['en' => ['title' => 'C1']]
        );
        $this->model->insert(
            ['slug' => 'count-2'],
            ['en' => ['title' => 'C2']]
        );

        $this->assertEquals(2, $this->model->countRows());
    }

    public function testCountRowsWithCondition(): void
    {
        $this->model->insert(
            ['slug' => 'active', 'is_active' => 1],
            ['en' => ['title' => 'A']]
        );
        $this->model->insert(
            ['slug' => 'inactive', 'is_active' => 0],
            ['en' => ['title' => 'I']]
        );

        $count = $this->model->countRows([
            'WHERE' => 'is_active=:active',
            'PARAM' => [':active' => 1]
        ]);

        $this->assertEquals(1, $count);
    }

    // --- deleteById ---

    public function testDeleteById(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'to-delete'],
            ['en' => ['title' => 'Delete Me']]
        );
        $id = $inserted['id'];

        $result = $this->model->deleteById($id);
        $this->assertTrue($result);

        $this->assertFalse($this->model->existsById($id));
    }

    public function testDeleteByIdCascadesContent(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'cascade-test'],
            ['en' => ['title' => 'Cascade'], 'mn' => ['title' => 'Каскад']]
        );
        $id = $inserted['id'];

        $this->model->deleteById($id);

        // Content table-с мөн устсан эсэхийг шалгах
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM test_localized_content WHERE parent_id=:id"
        );
        $stmt->execute([':id' => $id]);
        $this->assertEquals(0, (int)$stmt->fetchColumn());
    }

    // --- deactivateById ---

    public function testDeactivateById(): void
    {
        $inserted = $this->model->insert(
            ['slug' => 'deactivate-test', 'is_active' => 1],
            ['en' => ['title' => 'Active']]
        );
        $id = $inserted['id'];

        $result = $this->model->deactivateById($id);
        $this->assertTrue($result);

        $row = $this->model->getById($id);
        $this->assertEquals(0, $row['is_active']);
    }

    // --- select ---

    public function testSelectCustom(): void
    {
        $this->model->insert(
            ['slug' => 'select-test'],
            ['en' => ['title' => 'Select Test']]
        );

        $stmt = $this->model->select('*', [
            'WHERE' => 'p.slug=:slug',
            'PARAM' => [':slug' => 'select-test']
        ]);

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);
    }
}
