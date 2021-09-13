<?php

namespace codesaur\DataObject\Example;

/* DEV: v1.2021.03.15
 * 
 * This is an example script!
 */

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

require_once '../vendor/autoload.php';

use PDO;
use Exception;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class ExampleAccountModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
           (new Column('username', 'varchar', 65))->unique(),
            new Column('password', 'varchar', 255, ''),
            new Column('first_name', 'varchar', 50),
            new Column('last_name', 'varchar', 50),
            new Column('phone', 'varchar', 50),
            new Column('address', 'varchar', 200),
           (new Column('email', 'varchar', 65))->unique(),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 20) ,
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 20)
        ));
        
        $this->setTable('example_user');
    }
    
    function __initial()
    {
        $now_date = date('Y-m-d H:i:s');
        
        $table = $this->getName();
        $password = $this->quote(password_hash('secret', PASSWORD_BCRYPT));
        $query = "INSERT INTO $table(created_at,username,password,first_name,last_name,email)"
                . " VALUES('$now_date','admin',$password,'John','Doe','admin@example.com')";

        return $this->exec($query);
    }
}

class ExampleTranslationModel extends MultiModel
{
    function __construct(PDO $conn)
    {
        parent::__construct($conn);
        
        $this->setColumns(array(
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
           (new Column('created_by', 'bigint', 20))->foreignKey('example_user', 'id'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->foreignKey('example_user', 'id')
        ));
        $this->setContentColumns(array(
            new Column('title', 'varchar', 255)
        ));

        $this->setTable('example_translation');
    }
    
    function __initial()
    {
        $this->insert(array('keyword' => 'chat'), array('mn' => array('title' => 'Харилцан яриа'), 'en' => array('title' => 'Chat')));
        $this->insert(array('keyword' => 'accordion'), array('mn' => array('title' => 'Аккордеон'), 'en' => array('title' => 'Accordion')));
        $this->insert(array('keyword' => 'account'), array('mn' => array('title' => 'Хэрэглэгч'), 'en' => array('title' => 'Account')));
        $this->insert(array('keyword' => 'actions'), array('mn' => array('title' => 'Үйлдлүүд'), 'en' => array('title' => 'Actions')));
        $this->insert(array('keyword' => 'active'), array('mn' => array('title' => 'Идэвхитэй'), 'en' => array('title' => 'Active')));
        $this->insert(array('keyword' => 'add'), array('mn' => array('title' => 'Нэмэх'), 'en' => array('title' => 'Add')));
        $this->insert(array('keyword' => 'address'), array('mn' => array('title' => 'Хаяг'), 'en' => array('title' => 'Address')));
        $this->insert(array('keyword' => 'alerts'), array('mn' => array('title' => 'Мэдэгдлүүд'), 'en' => array('title' => 'Alerts')));
        $this->insert(array('keyword' => 'back'), array('mn' => array('title' => 'Буцах'), 'en' => array('title' => 'Back')));
        $this->insert(array('keyword' => 'banner'), array('mn' => array('title' => 'Баннер'), 'en' => array('title' => 'Banner')));
        $this->insert(array('keyword' => 'boxed'), array('mn' => array('title' => 'Хайрцагласан'), 'en' => array('title' => 'Boxed')));
        $this->insert(array('keyword' => 'cancel'), array('mn' => array('title' => 'Болих'), 'en' => array('title' => 'Cancel')));
        $this->insert(array('keyword' => 'category'), array('mn' => array('title' => 'Ангилал'), 'en' => array('title' => 'Category')));
        $this->insert(array('keyword' => 'change'), array('mn' => array('title' => 'Өөрчлөх'), 'en' => array('title' => 'Change')));
    }
}

try {
    $dsn = 'mysql:host=localhost;charset=utf8';
    $username = 'root';
    $passwd = '';
    $options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
    
    $pdo = new PDO($dsn, $username, $passwd, $options);
    echo 'connected to mysql...<br/>';
    
    $database = 'dataobject_example';
    if ($_SERVER['HTTP_HOST'] === 'localhost'
            && in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))
    ) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $pdo->quote('utf8_unicode_ci'));
    }

    $pdo->exec("USE $database");
    echo "starting to use database [$database]<br/>";
    
    $account = new ExampleAccountModel($pdo);
    $admin = $account->getRowBy(array('username' =>'admin'));
    if ($admin) {
        putenv("CODESAUR_ACCOUNT_ID={$admin['id']}");

        var_dump(array('admin' => $admin));
    }

    $uniq_account = uniqid('account');
    $new_account_id = $account->insert(array(
        'username' => $uniq_account,
        'password' => password_hash('pass', PASSWORD_BCRYPT),
        'first_name' => 'Random Guy',
        'phone' => uniqid(),
        'address' => 'Somewhere in Earth',
        'email' => "$uniq_account@example.com"
    ));

    var_dump(array('newly created account id: ' => $new_account_id));
    var_dump(array('newly created account: ' => $account->getById($new_account_id)));
    var_dump(array('delete account 3: ' => $account->deleteById(3)));
    putenv('CODESAUR_DB_KEEP_DELETE=true');
    var_dump(array('deactivate account 7: ' => $account->deleteById(7)));
    
    var_dump($account->update(array('address' => 'Ulaanbaatar'), array('WHERE' => 'is_active=1')));
    var_dump($account->updateById(15, array('first_name' => 'Not so random', 'id' => 1500)));
    
    $translation = new ExampleTranslationModel($pdo);

    echo "<br/><hr><br/>chat in mongolian => {$texts['chat']['mn']}<br/>";
    var_dump($translation->getById(3, 'mn'));
    
    putenv('CODESAUR_DB_KEEP_DELETE');
    var_dump($translation->deleteById(7));
    putenv('CODESAUR_DB_KEEP_DELETE=true');
    var_dump($translation->deleteById(8));
    var_dump($translation->update(['keyword' => 'golio'], ['mn' => ['title' => 'Голио'], 'en' => ['title' => 'Cicada'], 'de' => ['title' => 'die Heuschrecke']], array('WHERE' => 'p.id=4')));
    var_dump($translation->updateById(5, ['id' => 500], ['en' => ['title' => 'Hyperactive']]));
    
    $rows = $translation->getRows();
    $texts = array();
    foreach ($rows as $row) {
        $texts[$row['keyword']] = array_merge($texts[$row['keyword']] ?? [], $row['content']['title']);
    }
    echo "<br/><hr><br/>List of Translation texts<br/>";
    var_dump($texts);
    
    foreach ($translation->getRows(['ORDER BY' => 'p.keyword']) as $row) {
        var_dump($row);
    }
    
    echo "<br/><hr><br/><br/>";
    var_dump(array('list of accounts: ' => $account->getRows()));
} catch (Exception $ex) {
    die('<br/>{' . date('Y-m-d H:i:s') . '} Exception[' . $ex->getCode() . '] => ' . $ex->getMessage());
}
