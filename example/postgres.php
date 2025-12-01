<?php

namespace codesaur\DataObject\Example;

/**
 * postgres.php
 * -------------------------------------------------------------------------
 * codesaur/dataobject багцын PostgreSQL орчинд ажиллах бүрэн жишээ скрипт.
 *
 * Энэ файл дараах ажлуудыг хийж гүйцэтгэнэ:
 *
 *  1. PostgreSQL сервертэй холбогдох (PDO pgsql)
 *  2. ExampleUserModel → хэрэглэгчийн хүснэгт үүсгэх, админ үүсгэх
 *  3. ExampleTranslationModel → олон хэл дээр контент хадгалах хүснэгтүүдийг
 *     автоматаар үүсгэх (PRIMARY + CONTENT)
 *  4. CRUD жишээнүүд → insert, update, delete, deactivate
 *  5. LocalizedModel ашиглан олон хэлний struct бүхий өгөгдлийг харуулах
 *
 * PostgreSQL нь MySQL-тай харьцуулахад дараах давуу талтай:
 *  - SERIAL, BIGSERIAL төрөлтэй → AUTO_INCREMENT шаардлагагүй
 *  - RETURNING * маш хурдан, классик ORM аргачлалтай төгс нийцдэг
 *
 * @package codesaur\DataObject\Example
 */

\ini_set('display_errors', 'On');
\error_reporting(E_ALL);

// Autoload болон жишээ namespace-ийн psr-4 автомаппинг
$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', __DIR__);

use codesaur\DataObject\Example\ExampleUserModel;
use codesaur\DataObject\Example\ExampleTranslationModel;

try {
    /**
     * ---------------------------------------------------------------------
     * 1. PostgreSQL сервертэй холбогдох
     * ---------------------------------------------------------------------
     */
    $username = 'postgres';
    $passwd   = 'password'; // PostgreSQL хэрэглэгчийн нууц үг
    $database = 'dataobject_example';
    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_PERSISTENT         => false
    ];
    $pdo = new \PDO(
        "pgsql:host=localhost;dbname=$database;client_encoding=UTF8",
        $username,
        $passwd,
        $options
    );
    echo "connected to postgres as user [$username] and starting to use database [$database]...<br/>";

    /**
     * ---------------------------------------------------------------------
     * 2. ExampleUserModel → хэрэглэгчийн хүснэгт үүсгэх
     *    (хүснэгт байхгүй бол автоматаар үүсгэнэ)
     * ---------------------------------------------------------------------
     */
    $users = new ExampleUserModel($pdo);

    // Админ хэрэглэгч байгаа эсэхийг шалгах
    $admin = $users->getRowWhere(['username' => 'admin']);
    if ($admin) {
        \var_dump(['admin' => $admin]);
    }

    /**
     * ---------------------------------------------------------------------
     * 3. Шинэ хэрэглэгч нэмэх (Insert)
     * ---------------------------------------------------------------------
     */
    $uniq_user = \uniqid('user');

    $new_user = $users->insert([
        'username'   => $uniq_user,
        'password'   => \password_hash('pass', \PASSWORD_BCRYPT),
        'first_name' => 'Random Guy',
        'phone'      => \uniqid(),
        'address'    => 'Somewhere in Earth',
        'email'      => "$uniq_user@example.com",
        'created_by' => $admin['id']
    ]);
    \var_dump(['newly created user:' => $new_user]);


    /**
     * ---------------------------------------------------------------------
     * 4. CRUD жишээнүүд → Delete, Deactivate, Update
     * ---------------------------------------------------------------------
     */
    \var_dump(['delete user 3:' =>
        $users->deleteById(3)
    ]);

    \var_dump(['deactivate user 7:' =>
        $users->deactivateById(7, [
            'updated_at' => \date('Y-m-d H:i:s'),
            'updated_by' => $admin['id']
        ])
    ]);

    \var_dump(['update user 15:' =>
        $users->updateById(15, [
            'first_name' => 'Not so random',
            'id'         => 1500,       // ID-г өөрчлөх (PostgreSQL-д зөвшөөрөгдөнө)
            'updated_by' => $admin['id']
        ])
    ]);

    /**
     * ---------------------------------------------------------------------
     * 5. ExampleTranslationModel → олон хэлний хүснэгтүүд
     * ---------------------------------------------------------------------
     */
    $translation = new ExampleTranslationModel($pdo);

    // Localized мөр авах
    \var_dump($translation->getRowWhere(['p.id' => 1, 'p.is_active' => 1]));
    \var_dump($translation->getRowWhere(['p.id' => 1, 'c.code' => 'mn']));

    // Delete / deactivate
    \var_dump($translation->deleteById(7));

    \var_dump(['deactivate translation 8:' =>
        $translation->deactivateById(8, [
            'updated_at' => \date('Y-m-d H:i:s'),
            'updated_by' => $admin['id']
        ])
    ]);

    // Олон хэл дээр update хийх
    \var_dump([
        'update translation 4:' =>
        $translation->updateById(
            4,
            ['keyword' => 'golio', 'updated_by' => $admin['id']],
            [
                'mn' => ['title' => 'Голио'],
                'en' => ['title' => 'Cicada'],
                'de' => ['title' => 'die Heuschrecke']
            ]
        )
    ]);

    \var_dump([
        'update translation 5:' =>
        $translation->updateById(
            5,
            ['id' => 500, 'updated_by' => $admin['id']],
            ['en' => ['title' => 'Hyperactive']]
        )
    ]);


    /**
     * ---------------------------------------------------------------------
     * 6. Олон хэлний бүх translation текстүүдийг жагсааж харуулах
     * ---------------------------------------------------------------------
     */
    $rows = $translation->getRows([
        'WHERE'    => 'p.is_active=1',
        'ORDER BY' => 'p.id'
    ]);
    $texts = [];
    foreach ($rows as $row) {
        $texts[$row['keyword']] = \array_merge(
            $texts[$row['keyword']] ?? [],
            $row['localized']['title']
        );
    }
    echo '<br/><hr><br/>List of Translation texts<br/>';
    \var_dump($texts);
    echo "<br/><hr><br/>chat in mongolian => {$texts['chat']['mn']}<br/>";

    /**
     * ---------------------------------------------------------------------
     * 7. p.is_active=1 нөхцөлтэй бүх локалчилсан мөрийг хэвлэх
     * ---------------------------------------------------------------------
     */
    foreach ($translation->getRows([
        'WHERE'    => 'p.is_active=1',
        'ORDER BY' => 'p.keyword'
    ]) as $row) {
        \var_dump($row);
    }

    /**
     * ---------------------------------------------------------------------
     * 8. Идэвхтэй бүх хэрэглэгчийн жагсаалт
     * ---------------------------------------------------------------------
     */
    echo '<br/><hr><br/><br/>';
    \var_dump([
        'list of users:' =>
        $users->getRows([
            'WHERE'    => 'is_active=1',
            'ORDER BY' => 'id DESC'
        ])
    ]);
} catch (\Throwable $e) {
    /**
     * ---------------------------------------------------------------------
     * 9. Алдаа гарвал цаг+мессежийг хэвлээд гүйцэтгэлийг зогсооно
     * ---------------------------------------------------------------------
     */
    die(
        '<br/>{' . \date('Y-m-d H:i:s') .
        '} Error[' . $e->getCode() .
        '] => ' . $e->getMessage()
    );
}
