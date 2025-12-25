<?php

namespace codesaur\DataObject\Example;

/**
 * sqlite.php
 * -------------------------------------------------------------------------
 * codesaur/dataobject багцын SQLite орчинд ажиллах бүрэн жишээ скрипт.
 *
 * Энэ файл дараах үндсэн үе шатуудыг хийж гүйцэтгэнэ:
 *
 *  1. PDO ашиглан SQLite файлтай холбогдох
 *  2. dataobject_example.db нэртэй хөгжүүлэлтийн database файл үүсгэх (локал орчинд)
 *  3. ExampleUserModel → хэрэглэгчийн хүснэгт үүсгэх, админ хэрэглэгч нэмэх
 *  4. ExampleTranslationModel → олон хэлний контент бүхий хүснэгтүүдийг үүсгэх
 *  5. Insert / Update / Delete / Deactivate зэрэг CRUD жишээнүүд ажиллуулах
 *  6. LocalizedModel ашиглан олон хэл дээр хадгалсан өгөгдлийг харуулах
 *
 * Зориулалт:
 *  - DataObject багц хэрхэн ажилладагийг бүрэн харуулах бодит жишээ
 *  - codesaur-php проектуудын model/database abstraction-ийг турших
 *  - SQLite дээр тест хийх, хөгжүүлэлт хийх
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
     *  1. SQLite файлтай холбогдох
     * ---------------------------------------------------------------------
     */
    $dbFile = __DIR__ . '/dataobject_example.db';
    $dsn = "sqlite:$dbFile";
    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_PERSISTENT         => false
    ];
    $pdo = new \PDO($dsn, null, null, $options);
    
    // SQLite дээр foreign key constraints идэвхжүүлэх
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    echo "connected to SQLite database: $dbFile<br/>";

    /**
     * ---------------------------------------------------------------------
     *  2. ExampleUserModel → хэрэглэгчийн хүснэгт үүсгэх
     *     (хүснэгт байхгүй бол автоматаар үүсгэнэ)
     * ---------------------------------------------------------------------
     */
    $users = new ExampleUserModel($pdo);

    // Админ хэрэглэгч байгаа эсэхийг шалгах
    $admin = $users->getRowWhere(['username' => 'admin']);
    if (!$admin) {
        // Админ хэрэглэгч байхгүй бол үүсгэх
        $admin = $users->insert([
            'username'   => 'admin',
            'password'   => \password_hash('admin123', \PASSWORD_BCRYPT),
            'first_name' => 'Administrator',
            'phone'      => '1234567890',
            'address'    => 'System',
            'email'      => 'admin@example.com',
            'created_by' => 1
        ]);
    }
    debug($admin, 'Admin user');

    /**
     * ---------------------------------------------------------------------
     *  3. Шинэ хэрэглэгч нэмэх (Insert)
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
     *  4. Delete, Deactivate, Update гэх мэт CRUD жишээнүүд
     * ---------------------------------------------------------------------
     */
    // Delete жишээ (хэрэв ID байвал)
    $testUser = $users->getRowWhere(['id' => 3]);
    if ($testUser) {
        debug($users->deleteById(3), 'Delete user 3');
    }

    // Deactivate жишээ (хэрэв ID байвал)
    $testUser7 = $users->getRowWhere(['id' => 7]);
    if ($testUser7) {
        debug($users->deactivateById(7, [
            'updated_at' => \date('Y-m-d H:i:s'),
            'updated_by' => $admin['id']
        ]), 'Deactivate user 7');
    }

    // Update жишээ
    debug($users->updateById($new_user['id'], [
        'first_name' => 'Not so random',
        'updated_by' => $admin['id']
    ]), 'Update user');

    /**
     * ---------------------------------------------------------------------
     *  5. ExampleTranslationModel → олон хэлтэй хүснэгтүүдийг ашиглах
     * ---------------------------------------------------------------------
     */
    $translation = new ExampleTranslationModel($pdo);

    // Шинэ локалчилсан мөр нэмэх (keyword нь UNIQUE тул давхардлахаас сэргийлэх)
    $uniqueKeyword = 'hello_' . \uniqid();
    $existing = $translation->getRowWhere(['p.keyword' => 'hello']);
    if ($existing) {
        // 'hello' keyword аль хэдийн байвал одоогийн мөрийг ашиглана
        $newTranslation = $existing;
        $uniqueKeyword = 'hello';
    } else {
        // Шинэ мөр нэмэх
        $newTranslation = $translation->insert(
            ['keyword' => $uniqueKeyword, 'created_by' => $admin['id']],
            [
                'mn' => ['title' => 'Сайн уу'],
                'en' => ['title' => 'Hello'],
                'de' => ['title' => 'Hallo']
            ]
        );
    }
    debug($newTranslation, 'Translation (' . $uniqueKeyword . ')');

    // Localized мөр авах
    debug($translation->getRowWhere(['p.id' => $newTranslation['id'], 'p.is_active' => 1]), 'Get row (id=' . $newTranslation['id'] . ', active)');
    debug($translation->getRowWhere(['p.id' => $newTranslation['id'], 'c.code' => 'mn']), 'Get row (id=' . $newTranslation['id'] . ', code=mn)');
    $rowsByCode = $translation->getRowsByCode('en', ['WHERE' => 'p.id=:id', 'PARAM' => [':id' => $newTranslation['id']]]);
    debug($rowsByCode, 'Get rows by code (code=en, id=' . $newTranslation['id'] . ')');

    // Олон хэл дээр шинэчлэх (mn + en + de)
    // keyword-г шинэчлэхгүй (UNIQUE constraint-д хүрэхээс сэргийлэх)
    debug($translation->updateById(
        $newTranslation['id'],
        ['updated_by' => $admin['id']],
        [
            'mn' => ['title' => 'Мэндчилгээ'],
            'en' => ['title' => 'Greeting'],
            'de' => ['title' => 'Gruß']
        ]
    ), 'Update translation (mn+en+de)');

    // Зөвхөн зарим хэл шинэчлэх
    debug($translation->updateById(
        $newTranslation['id'],
        ['updated_by' => $admin['id']],
        ['en' => ['title' => 'Hello World']]
    ), 'Update translation (en only)');

    /**
     * ---------------------------------------------------------------------
     *  6. Бүх локалчилсан translation текстүүдийг жагсааж харуулах
     * ---------------------------------------------------------------------
     */
    $rows = $translation->getRows(['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.id']);
    $texts = [];
    foreach ($rows as $row) {
        // localized[lang][column] → title-уудыг хэлээр цуглуулах
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
    // Одоогийн keyword-г ашиглах
    if (isset($texts[$uniqueKeyword]['mn'])) {
        echo "<br/><hr>{$uniqueKeyword} in mongolian => {$texts[$uniqueKeyword]['mn']}<br/><hr>";
    } elseif (isset($texts['chat']['mn'])) {
        echo "<br/><hr>chat in mongolian => {$texts['chat']['mn']}<br/><hr>";
    }

    /**
     * ---------------------------------------------------------------------
     *  7. p.is_active=1 нөхцөлтэй бүх локалчилсан мөрийг хэвлэх
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
     *  8. Идэвхтэй бүх хэрэглэгчдийн жагсаалт
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
     *  9. Алдаа гарвал цаг+мессежийг хэвлээд гүйцэтгэлийг зогсооно
     * ---------------------------------------------------------------------
     */
    die(
        '<br/>{' . \date('Y-m-d H:i:s') .
        '} Error[' . $e->getCode() .
        '] => ' . $e->getMessage() .
        '<br/>File: ' . $e->getFile() .
        '<br/>Line: ' . $e->getLine()
    );
}
