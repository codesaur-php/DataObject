<?php

/* DEV: v1.2021.03.15
 * 
 * This is an example script!
 */

require_once '../vendor/autoload.php';

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

class AccountModel extends Model
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
           (new Column('created_by', 'bigint', 20))->foreignKey('user(id)'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->foreignKey('user(id)')
        ));
        
        $this->setCreateTable('user');
    }
    
    function initial()
    {
        $now_date = date('Y-m-d H:i:s');
        
        $table = $this->getName();
        $password = $this->quote(password_hash('secret', PASSWORD_BCRYPT));
        $query = "INSERT INTO $table (created_at,username,password,first_name,last_name,email)"
                . " VALUES ('$now_date','admin',$password,'John','Doe','admin@example.com')";

        return $this->exec($query);
    }
}

class TranslationModel extends MultiModel
{
    function __construct(PDO $conn)
    {
        parent::__construct($conn);
        
        $this->setMainColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
           (new Column('created_by', 'bigint', 20))->foreignKey('user(id)'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->foreignKey('user(id)')
        ));
        
        $this->setContentColumns(array(
            new Column('title', 'varchar', 255)
        ));
        
        $this->setCreateTable('default_translation');
    }
    
    function initial()
    {
        $this->insertContent(array('keyword' => 'chat'), array('mn' => array('title' => 'Харилцан яриа'), 'en' => array('title' => 'Chat')));
        $this->insertContent(array('keyword' => 'accordion'), array('mn' => array('title' => 'Аккордеон'), 'en' => array('title' => 'Accordion')));
        $this->insertContent(array('keyword' => 'account'), array('mn' => array('title' => 'Хэрэглэгч'), 'en' => array('title' => 'Account')));
        $this->insertContent(array('keyword' => 'actions'), array('mn' => array('title' => 'Үйлдлүүд'), 'en' => array('title' => 'Actions')));
        $this->insertContent(array('keyword' => 'active'), array('mn' => array('title' => 'Идэвхитэй'), 'en' => array('title' => 'Active')));
        $this->insertContent(array('keyword' => 'add'), array('mn' => array('title' => 'Нэмэх'), 'en' => array('title' => 'Add')));
        $this->insertContent(array('keyword' => 'address'), array('mn' => array('title' => 'Хаяг'), 'en' => array('title' => 'Address')));
        $this->insertContent(array('keyword' => 'alerts'), array('mn' => array('title' => 'Мэдэгдлүүд'), 'en' => array('title' => 'Alerts')));
        $this->insertContent(array('keyword' => 'back'), array('mn' => array('title' => 'Буцах'), 'en' => array('title' => 'Back')));
        $this->insertContent(array('keyword' => 'banner'), array('mn' => array('title' => 'Баннер'), 'en' => array('title' => 'Banner')));
        $this->insertContent(array('keyword' => 'boxed'), array('mn' => array('title' => 'Хайрцагласан'), 'en' => array('title' => 'Boxed')));
        $this->insertContent(array('keyword' => 'cancel'), array('mn' => array('title' => 'Болих'), 'en' => array('title' => 'Cancel')));
        $this->insertContent(array('keyword' => 'category'), array('mn' => array('title' => 'Ангилал'), 'en' => array('title' => 'Category')));
        $this->insertContent(array('keyword' => 'change'), array('mn' => array('title' => 'Өөрчлөх'), 'en' => array('title' => 'Change')));
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
    echo 'started using example database!<br/>';
    
    $account = new AccountModel($pdo);
    $admin = $account->getRow(array('username' =>'admin'));
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

    $translation = new TranslationModel($pdo);
    $rows = $translation->getRows();

    $texts = array();
    foreach ($rows as $row) {
        $texts[$row['keyword']] = array_merge($texts[$row['keyword']] ?? [], $row['content']['title']);
    }

    echo "chat in mongolian => {$texts['chat']['mn']}<br/>";

    var_dump($texts, $rows);
    var_dump($translation->getByID(3));
} catch (Exception $ex) {
    die('[' . date('Y-m-d H:i:s'). ' Error] ' . $ex->getMessage());
}
