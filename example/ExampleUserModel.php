<?php

namespace codesaur\DataObject\Example;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class ExampleUserModel
 *
 * Энэ класс нь хэрэглэгчийн хүснэгтийн моделийг тодорхойлно.
 *
 * Онцлогууд:
 *  - PRIMARY KEY bigint id
 *  - username болон email нь UNIQUE
 *  - created_by болон updated_by нь өөрийн хүснэгт рүү FK холбогдоно
 *  - Хүснэгт анхлан үүсэх үед админ хэрэглэгч автоматаар бүртгэгдэнэ
 *
 * @package codesaur\DataObject\Example
 */
class ExampleUserModel extends Model
{
    /**
     * ExampleUserModel constructor.
     *
     * Хүснэгтийн багануудыг тодорхойлж, хүснэгтийг үүсгэнэ.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        // Хүснэгтийн багана тодорхойлолт
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('username', 'varchar', 65))->unique(),
            new Column('password', 'varchar', 255),
            new Column('first_name', 'varchar', 50),
            new Column('last_name', 'varchar', 50),
            new Column('phone', 'varchar', 50),
           (new Column('address', 'varchar', 200))->default('Mongolia'),
           (new Column('email', 'varchar', 65))->unique(),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        // Хүснэгт үүсгэнэ
        $this->setTable('example_user');
    }

    /**
     * Хүснэгт анхлан шинээр үүсэх үед нэг удаа ажиллах тохиргоо.
     *
     * @return void|int
     */
    protected function __initial()
    {
        $table = $this->getName();

        // created_by → FK (self reference)
        $this->exec("ALTER TABLE $table 
            ADD CONSTRAINT {$table}_fk_created_by 
            FOREIGN KEY (created_by) REFERENCES $table(id) 
            ON DELETE SET NULL ON UPDATE CASCADE");

        // updated_by → FK (self reference)
        $this->exec("ALTER TABLE $table 
            ADD CONSTRAINT {$table}_fk_updated_by 
            FOREIGN KEY (updated_by) REFERENCES $table(id) 
            ON DELETE SET NULL ON UPDATE CASCADE");

        // Админ хэрэглэгчийг автоматаар үүсгэнэ
        $now = \date('Y-m-d H:i:s');
        $password = $this->quote(password_hash('secret', \PASSWORD_BCRYPT));

        $query =
            "INSERT INTO $table(created_at,username,password,first_name,email) 
             VALUES('$now','admin',$password,'Наранхүү','admin@example.com')";

        return $this->exec($query);
    }

    /**
     * Insert хийх үед created_at автоматаар тавина.
     *
     * @param array $record
     * @return array|false
     */
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }

    /**
     * Update хийх үед updated_at автоматаар тавина.
     *
     * @param int $id
     * @param array $record
     * @return array|false
     */
    public function updateById(int $id, array $record): array|false
    {
        if (!isset($record['updated_at'])) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        return parent::updateById($id, $record);
    }
}
