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
 *  3. ExampleUserModel -> хэрэглэгчийн хүснэгт үүсгэх, админ хэрэглэгч нэмэх
 *  4. ExampleTranslationModel -> олон хэлний контент бүхий хүснэгтүүдийг үүсгэх
 *  5. Insert / Update / Delete / Deactivate зэрэг CRUD жишээнүүд ажиллуулах
 *  6. LocalizedModel ашиглан олон хэл дээр хадгалсан өгөгдлийг харуулах
 *
 * Зориулалт:
 *  - DataObject багц хэрхэн ажилладагийг бүрэн харуулах бодит жишээ
 *  - codesaur-php проектуудын model/database abstraction-ийг турших
 *
 * PHP >= 8.2.1 шаардлагатай.
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
     *  3. ExampleUserModel -> хэрэглэгчийн хүснэгт үүсгэх
     *     (хүснэгт байхгүй бол автоматаар үүсгэнэ)
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
    debug($new_user, 'Newly created user');

    /**
     * ---------------------------------------------------------------------
     *  5. Delete, Deactivate, Update гэх мэт CRUD жишээнүүд
     * ---------------------------------------------------------------------
     */
    debug($users->deleteById(3), 'Delete user 3');

    debug($users->deactivateById(7, [
        'updated_at' => \date('Y-m-d H:i:s'),
        'updated_by' => $admin['id']
    ]), 'Deactivate user 7');

    debug($users->updateById(15, [
        'first_name' => 'Not so random',
        'id'         => 1500,       // ID-г өөрчлөх
        'updated_by' => $admin['id']
    ]), 'Update user 15');

    /**
     * ---------------------------------------------------------------------
     *  6. ExampleTranslationModel -> олон хэлтэй хүснэгтүүдийг ашиглах
     * ---------------------------------------------------------------------
     */
    $translation = new ExampleTranslationModel($pdo);

    // Localized мөр авах
    debug($translation->getRowWhere(['p.id' => 1, 'p.is_active' => 1]), 'Get row (id=1, active)');
    debug($translation->getRowWhere(['p.id' => 1, 'c.code' => 'mn']), 'Get row (id=1, code=mn)');
    $rowsByCode = $translation->getRowsByCode('en', ['WHERE' => 'p.id=1']);
    debug($rowsByCode, 'Get rows by code (code=en, id=1)');

    // Delete / deactivate
    debug($translation->deleteById(7), 'Delete translation 7');

    debug($translation->deactivateById(8, [
        'updated_at' => \date('Y-m-d H:i:s'),
        'updated_by' => $admin['id']
    ]), 'Deactivate translation 8');

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
     *  7. Бүх локалчилсан translation текстүүдийг жагсааж харуулах
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
     *  8. p.is_active=1 нөхцөлтэй бүх локалчилсан мөрийг хэвлэх
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
     *  9. Идэвхтэй бүх хэрэглэгчдийн жагсаалт
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
     *  10. Алдаа гарвал цаг+мессежийг хэвлээд гүйцэтгэлийг зогсооно
     * ---------------------------------------------------------------------
     */
    die(
        '<br/>{' . \date('Y-m-d H:i:s') .
        '} Error[' . $e->getCode() .
        '] => ' . $e->getMessage()
    );
}
