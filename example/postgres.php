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
 *  2. ExampleUserModel -> хэрэглэгчийн хүснэгт үүсгэх, админ хэрэглэгч шалгах
 *  3. ExampleTranslationModel -> олон хэл дээр контент хадгалах хүснэгтүүдийг
 *     автоматаар үүсгэх (PRIMARY + CONTENT)
 *  4. CRUD жишээнүүд -> insert, update, delete, deactivate
 *  5. LocalizedModel ашиглан олон хэлний struct бүхий өгөгдлийг харуулах
 *
 * PostgreSQL нь MySQL-тай харьцуулахад дараах давуу талтай:
 *  - SERIAL, BIGSERIAL төрөлтэй -> AUTO_INCREMENT шаардлагагүй
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

/**
 * Human-readable debug output helper
 *
 * @param mixed $data Хэвлэх өгөгдөл
 * @param string|null $label Шошго/гарчиг
 * @return void
 */
function debug($data, ?string $label = null): void
{
    echo '<pre style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px; overflow-x: auto;">';
    if ($label !== null) {
        echo '<strong style="color: #333;">' . \htmlspecialchars($label) . ':</strong><br/>';
    }
    echo \htmlspecialchars(\print_r($data, true));
    echo '</pre>';
}

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
     * 2. ExampleUserModel -> хэрэглэгчийн хүснэгт үүсгэх
     *    (хүснэгт байхгүй бол автоматаар үүсгэнэ)
     * ---------------------------------------------------------------------
     */
    $users = new ExampleUserModel($pdo);

    // Админ хэрэглэгч байгаа эсэхийг шалгах
    $admin = $users->getRowWhere(['username' => 'admin']);
    if ($admin) {
        debug(['admin' => $admin]);
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
    debug($new_user, 'Newly created user');


    /**
     * ---------------------------------------------------------------------
     * 4. getById, existsById, countRows жишээнүүд
     * ---------------------------------------------------------------------
     */
    debug($users->getById($new_user['id']), 'getById(' . $new_user['id'] . ')');
    debug($users->existsById($new_user['id']), 'existsById(' . $new_user['id'] . ')');
    debug($users->existsById(999999), 'existsById(999999)');
    debug($users->countRows(['WHERE' => 'is_active=1']), 'countRows (active users)');

    /**
     * ---------------------------------------------------------------------
     * 5. CRUD жишээнүүд -> Delete, Deactivate, Update
     * ---------------------------------------------------------------------
     */
    debug($users->deleteById(3), 'Delete user 3');

    try {
        debug($users->deactivateById(7, [
            'updated_at' => \date('Y-m-d H:i:s'),
            'updated_by' => $admin['id']
        ]), 'Deactivate user 7');
    } catch (\Exception $e) {
        debug($e->getMessage(), 'Deactivate user 7');
    }

    debug($users->updateById(15, [
        'first_name' => 'Not so random',
        'id'         => 1500,       // ID-г өөрчлөх (PostgreSQL-д зөвшөөрөгдөнө)
        'updated_by' => $admin['id']
    ]), 'Update user 15');

    /**
     * ---------------------------------------------------------------------
     * 6. ExampleTranslationModel -> олон хэлний хүснэгтүүд
     * ---------------------------------------------------------------------
     */
    $translation = new ExampleTranslationModel($pdo);

    // getById, existsById, countRows (LocalizedModel)
    debug($translation->getById(1), 'translation->getById(1)');
    debug($translation->existsById(1), 'translation->existsById(1)');
    debug($translation->countRows(['WHERE' => 'is_active=1']), 'translation->countRows (active)');

    // Localized мөр авах
    debug($translation->getRowWhere(['p.id' => 1, 'p.is_active' => 1]), 'Get row (id=1, active)');
    debug($translation->getRowWhere(['p.id' => 1, 'c.code' => 'mn']), 'Get row (id=1, code=mn)');
    $rowsByCode = $translation->getRowsByCode('en', ['WHERE' => 'p.id=1']);
    debug($rowsByCode, 'Get rows by code (code=en, id=1)');

    // Delete / deactivate
    debug($translation->deleteById(7), 'Delete translation 7');

    try {
        debug($translation->deactivateById(8, [
            'updated_at' => \date('Y-m-d H:i:s'),
            'updated_by' => $admin['id']
        ]), 'Deactivate translation 8');
    } catch (\Exception $e) {
        debug($e->getMessage(), 'Deactivate translation 8');
    }

    // Олон хэл дээр шинэчлэх (mn + en + de)
    debug($translation->updateById(
        4,
        ['keyword' => 'golio', 'updated_by' => $admin['id']],
        [
            'mn' => ['title' => 'Голио'],
            'en' => ['title' => 'Cicada'],
            'de' => ['title' => 'die Heuschrecke']
        ]
    ), 'Update translation 4 (mn+en+de)');

    // Зөвхөн зарим хэл шинэчлэх
    debug($translation->updateById(
        5,
        ['id' => 500, 'updated_by' => $admin['id']],
        ['en' => ['title' => 'Hyperactive']]
    ), 'Update translation 5 (en only)');

    /**
     * ---------------------------------------------------------------------
     * 6. Олон хэлний бүх translation текстүүдийг жагсааж харуулах
     * ---------------------------------------------------------------------
     */
    $rows = $translation->getRows(['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.id']);
    $texts = [];
    foreach ($rows as $row) {
        // localized[lang][column] -> title-уудыг хэлээр цуглуулах
        $titleByLang = [];
        foreach ($row['localized'] ?? [] as $lang => $content) {
            if (isset($content['title'])) {
                $titleByLang[$lang] = $content['title'];
            }
        }
        $texts[$row['keyword']] = \array_merge(
           $texts[$row['keyword']] ?? [],
           $titleByLang
        );
    }
    echo '<br/><hr><br/>';
    debug($texts, 'List of Translation texts');
    echo "<br/><hr>chat in mongolian => {$texts['chat']['mn']}<br/><hr>";

    /**
     * ---------------------------------------------------------------------
     * 7. p.is_active=1 нөхцөлтэй бүх локалчилсан мөрийг хэвлэх
     * ---------------------------------------------------------------------
     */
    foreach ($translation->getRows([
        'WHERE'    => 'p.is_active=1',
        'ORDER BY' => 'p.keyword'
    ]) as $row) {
        debug($row, 'Translation row');
    }

    /**
     * ---------------------------------------------------------------------
     * 8. Идэвхтэй бүх хэрэглэгчийн жагсаалт
     * ---------------------------------------------------------------------
     */
    echo '<br/><hr><br/><br/>';
    debug($users->getRows([
        'WHERE'    => 'is_active=1',
        'ORDER BY' => 'id DESC'
    ]), 'List of active users');
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
