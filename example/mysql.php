<?php

namespace codesaur\DataObject\Example;

/**
 * mysql.php
 * -------------------------------------------------------------------------
 * codesaur/dataobject багцын MySQL орчинд ажиллах бүрэн жишээ скрипт.
 *
 * Энэ файл дараах үндсэн үе шатуудыг хийж гүйцэтгэнэ:
 *
 *  1. PDO ашиглан MySQL сервертэй холбогдох
 *  2. dataobject_example нэртэй хөгжүүлэлтийн database үүсгэх (локал орчинд)
 *  3. ExampleUserModel → хэрэглэгчийн хүснэгт үүсгэх, админ хэрэглэгч нэмэх
 *  4. ExampleTranslationModel → олон хэлний контент бүхий хүснэгтүүдийг үүсгэх
 *  5. Insert / Update / Delete / Deactivate зэрэг CRUD жишээнүүд ажиллуулах
 *  6. LocalizedModel ашиглан олон хэл дээр хадгалсан өгөгдлийг харуулах
 *
 * Зориулалт:
 *  - DataObject багц хэрхэн ажилладагийг бүрэн харуулах бодит жишээ
 *  - codesaur-php проектуудын model/database abstraction-ийг турших
 *
 * PHP ≥ 8.2.1 шаардлагатай.
 *
 * @package codesaur\DataObject\Example
 */

\ini_set('display_errors', 'On');
\error_reporting(E_ALL);

// Autoload болон namespace mapping бүртгэх
$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', __DIR__);

use codesaur\DataObject\Example\ExampleUserModel;
use codesaur\DataObject\Example\ExampleTranslationModel;

try {
    /**
     * ---------------------------------------------------------------------
     *  1. MySQL сервертэй холбогдох
     * ---------------------------------------------------------------------
     */
    $dsn = 'mysql:host=localhost;charset=utf8mb4';
    $username = 'root';
    $passwd = '';
    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_PERSISTENT         => false
    ];
    $pdo = new \PDO($dsn, $username, $passwd, $options);
    echo "connected to mysql...<br/>";

    /**
     * ---------------------------------------------------------------------
     *  2. Локал орчинд database автоматаар үүсгэх (хэрвээ байхгүй бол)
     * ---------------------------------------------------------------------
     */
    $database = 'dataobject_example';
    if (\in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE utf8mb4_unicode_ci");
    }
    $pdo->exec("USE $database");
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    echo "starting to use database [$database]<br/>";

    /**
     * ---------------------------------------------------------------------
     *  3. ExampleUserModel → хэрэглэгчийн хүснэгт үүсгэх
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
     *  4. Шинэ хэрэглэгч нэмэх (Insert)
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
     *  5. Delete, Deactivate, Update гэх мэт CRUD жишээнүүд
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
            'id'         => 1500,       // ID-г өөрчлөх
            'updated_by' => $admin['id']
        ])
    ]);


    /**
     * ---------------------------------------------------------------------
     *  6. ExampleTranslationModel → олон хэлтэй хүснэгтүүдийг ашиглах
     * ---------------------------------------------------------------------
     */
    $translation = new ExampleTranslationModel($pdo);

    // Нэг мөр авах (PRIMARY + LOCALIZED)
    \var_dump($translation->getRowWhere(['p.id' => 1, 'p.is_active' => 1]));
    \var_dump($translation->getRowWhere(['p.id' => 1, 'c.code' => 'mn']));

    // Delete
    \var_dump($translation->deleteById(7));
    
    // Deactivate
    \var_dump(['deactivate translation 8:' =>
        $translation->deactivateById(8, [
            'updated_at' => \date('Y-m-d H:i:s'),
            'updated_by' => $admin['id']
        ])
    ]);

    // Олон хэл дээр шинэчлэх (mn + en + de)
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

    // Зөвхөн зарим хэл шинэчлэх
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
     *  7. Бүх локалчилсан translation текстүүдийг жагсааж харуулах
     * ---------------------------------------------------------------------
     */
    $rows = $translation->getRows(['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.id']);
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
     *  8. Translation хүснэгтийн бүх мөрийг харуулах
     * ---------------------------------------------------------------------
     */
    foreach ($translation->getRows(['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.keyword']) as $row) {
        \var_dump($row);
    }

    /**
     * ---------------------------------------------------------------------
     *  9. Идэвхтэй бүх хэрэглэгчдийн жагсаалт
     * ---------------------------------------------------------------------
     */
    echo '<br/><hr><br/><br/>';
    \var_dump(['list of users:' =>
        $users->getRows(['WHERE' => 'is_active=1', 'ORDER BY' => 'id DESC'])
    ]);    
} catch (\Throwable $e) {
    // Алдааны дэлгэрэнгүй мэдээллийг хэвлэж зогсооно
    die(
        '<br/>{' . \date('Y-m-d H:i:s') . 
        '} Error[' . $e->getCode() . 
        '] => ' . $e->getMessage()
    );
}
