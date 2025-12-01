<?php

namespace codesaur\DataObject\Example;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class ExampleTranslationModel
 *
 * Энэ модель нь олон хэл дээр хадгалах контент бүхий
 * жишээ translation системийг тодорхойлно.
 *
 * Архитектур:
 *  - PRIMARY TABLE: example_translation
 *    → keyword, is_active, timestamps
 *
 *  - CONTENT TABLE: example_translation_content
 *    → parent_id, code(mn/en…), title
 *
 * @package codesaur\DataObject\Example
 */
class ExampleTranslationModel extends LocalizedModel
{
    /**
     * ExampleTranslationModel constructor.
     *
     * Хүснэгтийн баганууд болон контент багануудыг тодорхойлж,
     * 2 хүснэгтийг үүсгэнэ.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        // Primary table columns
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('keyword', 'varchar', 128))->unique(),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        // Localized content columns (title)
        $this->setContentColumns([
            new Column('title', 'varchar', 255)
        ]);

        // Create both tables
        $this->setTable('example_translation');
    }

    /**
     * Хүснэгт үүсгэгдсэн даруй хийгдэх анхны тохиргоо.
     *
     * @return void
     */
    protected function __initial()
    {
        // Хэрвээ example_user хүснэгт байхгүй бол үүсгэнэ
        if (!$this->hasTable('example_user')) {
            new ExampleUserModel($this->pdo);
        }

        $table = $this->getName();

        // created_by → FK example_user.id
        $this->setForeignKeyChecks(false);
        $this->exec(
            "ALTER TABLE $table 
             ADD CONSTRAINT {$table}_fk_created_by 
             FOREIGN KEY (created_by) REFERENCES example_user(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );

        // updated_by → FK example_user.id
        $this->exec(
            "ALTER TABLE $table 
             ADD CONSTRAINT {$table}_fk_updated_by 
             FOREIGN KEY (updated_by) REFERENCES example_user(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );
        $this->setForeignKeyChecks(true);

        // Анхны олон хэлтэй түлхүүрүүдийг оруулна
        $this->insert(['keyword' => 'chat'], ['mn' => ['title' => 'Харилцан яриа'], 'en' => ['title' => 'Chat']]);
        $this->insert(['keyword' => 'accordion'], ['mn' => ['title' => 'Аккордеон'], 'en' => ['title' => 'Accordion']]);
        $this->insert(['keyword' => 'account'], ['mn' => ['title' => 'Хэрэглэгч'], 'en' => ['title' => 'Account']]);
        $this->insert(['keyword' => 'actions'], ['mn' => ['title' => 'Үйлдлүүд'], 'en' => ['title' => 'Actions']]);
        $this->insert(['keyword' => 'active'], ['mn' => ['title' => 'Идэвхитэй'], 'en' => ['title' => 'Active']]);
        $this->insert(['keyword' => 'add'], ['mn' => ['title' => 'Нэмэх'], 'en' => ['title' => 'Add']]);
        $this->insert(['keyword' => 'address'], ['mn' => ['title' => 'Хаяг'], 'en' => ['title' => 'Address']]);
        $this->insert(['keyword' => 'alerts'], ['mn' => ['title' => 'Мэдэгдлүүд'], 'en' => ['title' => 'Alerts']]);
        $this->insert(['keyword' => 'back'], ['mn' => ['title' => 'Буцах'], 'en' => ['title' => 'Back']]);
        $this->insert(['keyword' => 'banner'], ['mn' => ['title' => 'Баннер'], 'en' => ['title' => 'Banner']]);
        $this->insert(['keyword' => 'boxed'], ['mn' => ['title' => 'Хайрцагласан'], 'en' => ['title' => 'Boxed']]);
        $this->insert(['keyword' => 'cancel'], ['mn' => ['title' => 'Болих'], 'en' => ['title' => 'Cancel']]);
        $this->insert(['keyword' => 'category'], ['mn' => ['title' => 'Ангилал'], 'en' => ['title' => 'Category']]);
        $this->insert(['keyword' => 'change'], ['mn' => ['title' => 'Өөрчлөх'], 'en' => ['title' => 'Change']]);
    }

    /**
     * Insert хийх үед created_at автоматаар тавина.
     *
     * @param array $record
     * @param array $content
     * @return array|false
     */
    public function insert(array $record, array $content): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record, $content);
    }

    /**
     * Update хийх үед updated_at автоматаар тавина.
     *
     * @param int $id
     * @param array $record
     * @param array $content
     * @return array|false
     */
    public function updateById(int $id, array $record, array $content): array|false
    {
        if (!isset($record['updated_at'])) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        return parent::updateById($id, $record, $content);
    }
}
