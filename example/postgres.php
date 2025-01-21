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
use codesaur\DataObject\LocalizedModel;

class ExampleUserModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
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
        
        $this->setTable('example_user');
    }
    
    protected function __initial()
    {
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $table(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $table(id) ON DELETE SET NULL ON UPDATE CASCADE");

        $now_date = \date('Y-m-d H:i:s');
        $password = $this->quote(password_hash('secret', \PASSWORD_BCRYPT));
        $query =
            "INSERT INTO $table(created_at,username,password,first_name,email) " .
            "VALUES('$now_date','admin',$password,'Наранхүү','admin@example.com')";

        return $this->exec($query);
    }
}

class ExampleTranslationModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('keyword', 'varchar', 128))->unique(),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        $this->setContentColumns([
            new Column('title', 'varchar', 255)
        ]);

        $this->setTable('example_translation');
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
    $dsn = 'pgsql:host=localhost';
    $username = 'postgres';
    $passwd = 'Free4all$'; // postgres user password
    $database = 'dataobject_example';
    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_PERSISTENT => false
    ];
    $pdo = new \PDO("pgsql:host=localhost;dbname=$database", $username, $passwd, $options);
    echo "connected to postgres as user [$username] and starting to use database [$database]...<br/>";
    
    $users = new ExampleUserModel($pdo);
    $admin = $users->getRowBy(['username' =>'admin']);
    if ($admin) {
        \var_dump(['admin' => $admin]);
    }

    $uniq_user = \uniqid('user');
    $new_user = $users->insert([
        'username' => $uniq_user,
        'password' => \password_hash('pass', \PASSWORD_BCRYPT),
        'first_name' => 'Random Guy',
        'phone' => \uniqid(),
        'address' => 'Somewhere in Earth',
        'email' => "$uniq_user@example.com"
    ]);
    
    \var_dump(['newly created user ' => $new_user]);
    \var_dump(['delete user 3: ' => $users->deleteById(3)]);
    \var_dump(['deactivate user 7: ' => $users->deactivateById(7)]);
    \var_dump(['update user 15: ' => $users->updateById(15, ['first_name' => 'Not so random', 'id' => 1500])]);
    
    $translation = new ExampleTranslationModel($pdo);
    \var_dump($translation->getById(1), $translation->getById(1, 'mn'));
    \var_dump($translation->deleteById(7));
    \var_dump($translation->deactivateById(8));
    \var_dump($translation->updateById(4, ['keyword' => 'golio'], ['mn' => ['title' => 'Голио'], 'en' => ['title' => 'Cicada'], 'de' => ['title' => 'die Heuschrecke']]));
    \var_dump($translation->updateById(5, ['id' => 500], ['en' => ['title' => 'Hyperactive']]));
    $rows = $translation->getRows();
    $texts = [];
    foreach ($rows as $row) {
        $texts[$row['keyword']] = \array_merge($texts[$row['keyword']] ?? [], $row['localized']['title']);
    }
    echo '<br/><hr><br/>List of Translation texts<br/>';
    \var_dump($texts);
    
    echo "<br/><hr><br/>chat in mongolian => {$texts['chat']['mn']}<br/>";
    
    foreach ($translation->getRows(['ORDER BY' => 'p.keyword']) as $row) {
        \var_dump($row);
    }
    
    echo '<br/><hr><br/><br/>';
    \var_dump(['list of users: ' => $users->getRows()]);
} catch (\Throwable $e) {
    die('<br/>{' . \date('Y-m-d H:i:s') . '} Error[' . $e->getCode() . '] => ' . $e->getMessage());
}
