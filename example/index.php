<?php

namespace codesaur\DataObject\Example;

/* DEV: v1.2021.03.15
 * 
 * This is an example script!
 */

\ini_set('display_errors', 'On');
\error_reporting(\E_ALL);

require_once '../vendor/autoload.php';

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class ExampleAccountModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
           (new Column('username', 'varchar', 65))->unique(),
            new Column('password', 'varchar', 255, ''),
            new Column('first_name', 'varchar', 50),
            new Column('last_name', 'varchar', 50),
            new Column('phone', 'varchar', 50),
            new Column('address', 'varchar', 200),
           (new Column('email', 'varchar', 65))->unique(),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('example_user', 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $table(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $table(id) ON DELETE SET NULL ON UPDATE CASCADE");

        $now_date = \date('Y-m-d H:i:s');
        $password = $this->quote(password_hash('secret', \PASSWORD_BCRYPT));
        $query =
            "INSERT INTO $table(created_at,username,password,first_name,last_name,email) " .
            "VALUES('$now_date','admin',$password,'John','Doe','admin@example.com')";

        return $this->exec($query);
    }
}

class ExampleTranslationModel extends MultiModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        $this->setContentColumns([
            new Column('title', 'varchar', 255)
        ]);

        $this->setTable('example_translation', 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $table = $this->getName();
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES example_user(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES example_user(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);

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
}

try {
    $dsn = 'mysql:host=localhost;charset=utf8';
    $username = 'root';
    $passwd = '';
    $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
    
    $pdo = new \PDO($dsn, $username, $passwd, $options);
    echo 'connected to mysql...<br/>';
    
    $database = 'dataobject_example';
    if (\in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $pdo->quote('utf8_unicode_ci'));
    }

    $pdo->exec("USE $database");
    echo "starting to use database [$database]<br/>";
    
    $account = new ExampleAccountModel($pdo);
    $admin = $account->getRowBy(['username' =>'admin']);
    if ($admin) {
        \putenv("CODESAUR_ACCOUNT_ID={$admin['id']}");
        \var_dump(['admin' => $admin]);
    }

    $uniq_account = \uniqid('account');
    $new_account_id = $account->insert([
        'username' => $uniq_account,
        'password' => \password_hash('pass', \PASSWORD_BCRYPT),
        'first_name' => 'Random Guy',
        'phone' => \uniqid(),
        'address' => 'Somewhere in Earth',
        'email' => "$uniq_account@example.com"
    ]);

    \var_dump(['newly created account id: ' => $new_account_id]);
    \var_dump(['newly created account: ' => $account->getById($new_account_id)]);
    
    $_ENV['CODESAUR_DELETE_DEACTIVATE'] = false;
    \var_dump(['delete account 3: ' => $account->deleteById(3)]);
    
    $_ENV['CODESAUR_DELETE_DEACTIVATE'] = true;
    \var_dump(['deactivate account 7: ' => $account->deleteById(7)]);
    
    \var_dump($account->update(['address' => 'Ulaanbaatar'], ['WHERE' => 'is_active=1']));
    \var_dump($account->updateById(15, ['first_name' => 'Not so random', 'id' => 1500]));
    
    $translation = new ExampleTranslationModel($pdo);

    \var_dump($translation->getById(1, 'mn'));
    
    $_ENV['CODESAUR_DELETE_DEACTIVATE'] = false;
    \var_dump($translation->deleteById(7));

    $_ENV['CODESAUR_DELETE_DEACTIVATE'] = true;
    \var_dump($translation->deleteById(8));
   
    \var_dump($translation->update(['keyword' => 'golio'], ['mn' => ['title' => 'Голио'], 'en' => ['title' => 'Cicada'], 'de' => ['title' => 'die Heuschrecke']], ['WHERE' => 'p.id=4']));
    \var_dump($translation->updateById(5, ['id' => 500], ['en' => ['title' => 'Hyperactive']]));
    
    $rows = $translation->getRows();
    $texts = [];
    foreach ($rows as $row) {
        $texts[$row['keyword']] = \array_merge($texts[$row['keyword']] ?? [], $row['content']['title']);
    }
    echo '<br/><hr><br/>List of Translation texts<br/>';
    \var_dump($texts);
    
    echo "<br/><hr><br/>chat in mongolian => {$texts['chat']['mn']}<br/>";
    
    foreach ($translation->getRows(['ORDER BY' => 'p.keyword']) as $row) {
        \var_dump($row);
    }
    
    echo '<br/><hr><br/><br/>';
    \var_dump(['list of accounts: ' => $account->getRows()]);
} catch (\Throwable $e) {
    die('<br/>{' . \date('Y-m-d H:i:s') . '} Error[' . $e->getCode() . '] => ' . $e->getMessage());
}
