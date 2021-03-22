<?php

/* DEV: v1.2021.03.15
 * 
 * This is an example script!
 */

require_once '../vendor/autoload.php';

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

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
        
        $this->setTable('user');
    }
    
    public function initial()
    {
        $now_date = date('Y-m-d H:i:s');
        
        $table = $this->getName();
        $password = $this->quote(password_hash('secret', PASSWORD_BCRYPT));
        $query = "INSERT INTO $table (created_at,username,password,first_name,last_name,email)" .
                " VALUES ('$now_date','admin',$password,'John','Doe','admin@example.com')";

        return $this->exec($query);
    }
}

class TranslationModel extends MultiModel
{
    function __construct(PDO $conn)
    {
        parent::__construct($conn);
        
        $this->setMainColumns(array(
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('type', 'int', 4, 0),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
           (new Column('created_by', 'bigint', 20))->foreignKey('user(id)'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->foreignKey('user(id)')
        ));
        
        $this->setContentColumns(array(
            new Column('title', 'varchar', 255)
        ));
        
        $this->setTable('default_translation');
    }
    
    public function initial()
    {
        $this->inserts(array('keyword' => 'chat'), array('mn' => array('title' => 'Харилцан яриа'), 'en' => array('title' => 'Chat')));
        $this->inserts(array('keyword' => 'accordion'), array('mn' => array('title' => 'Аккордеон'), 'en' => array('title' => 'Accordion')));
        $this->inserts(array('keyword' => 'account'), array('mn' => array('title' => 'Хэрэглэгч'), 'en' => array('title' => 'Account')));
        $this->inserts(array('keyword' => 'actions'), array('mn' => array('title' => 'Үйлдлүүд'), 'en' => array('title' => 'Actions')));
        $this->inserts(array('keyword' => 'active'), array('mn' => array('title' => 'Идэвхитэй'), 'en' => array('title' => 'Active')));
        $this->inserts(array('keyword' => 'add'), array('mn' => array('title' => 'Нэмэх'), 'en' => array('title' => 'Add')));
        $this->inserts(array('keyword' => 'address'), array('mn' => array('title' => 'Хаяг'), 'en' => array('title' => 'Address')));
        $this->inserts(array('keyword' => 'alerts'), array('mn' => array('title' => 'Мэдэгдлүүд'), 'en' => array('title' => 'Alerts')));
        $this->inserts(array('keyword' => 'back'), array('mn' => array('title' => 'Буцах'), 'en' => array('title' => 'Back')));
        $this->inserts(array('keyword' => 'banner'), array('mn' => array('title' => 'Баннер'), 'en' => array('title' => 'Banner')));
        $this->inserts(array('keyword' => 'boxed'), array('mn' => array('title' => 'Хайрцагласан'), 'en' => array('title' => 'Boxed')));
        $this->inserts(array('keyword' => 'cancel'), array('mn' => array('title' => 'Болих'), 'en' => array('title' => 'Cancel')));
        $this->inserts(array('keyword' => 'category'), array('mn' => array('title' => 'Ангилал'), 'en' => array('title' => 'Category')));
        $this->inserts(array('keyword' => 'change'), array('mn' => array('title' => 'Өөрчлөх'), 'en' => array('title' => 'Change')));
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
} catch (Exception $ex) {
    die('MySQL error => ' . $ex->getMessage());
}

$account = new AccountModel($pdo);
$admin = $account->getBy('username', 'admin');
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
    $texts[$row['keyword']] = array_merge($texts[$row['keyword']] ?? [], $row['title']);
}

echo "chat in mongolian => {$texts['chat']['mn']}<br/>";

var_dump($texts);
