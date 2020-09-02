# MkSQL
![GitHub](https://img.shields.io/github/license/zrny/mksql)
![Packagist Downloads](https://img.shields.io/packagist/dm/zrny/mksql)
![Travis (.com)](https://img.shields.io/travis/com/zrny/mksql)
![Packagist Version](https://img.shields.io/packagist/v/zrny/mksql)

MkSQL is an SQL table updater. It allows you to define table 
structure in code instead of fiddling with Adminer (or PHPMyAdmin).

It's a good tool for developing, but I would better disable it in production
and setup production tables by hand to save some runtime resources, OR I would
create separate script to run it only once which is enough to 
make everything up to date.

##### Installation

`composer require zrny/mksql`

##### Supported Drivers: 

- [✅ MySQL](https://www.mysql.com) 
- [✅ SQLite](https://www.sqlite.org/index.html) 

Planned support:

- [❌ PgSQL](https://www.postgresql.org) (*one day... or another...*)

No other drivers are planned to be implemented, **change my mind**!

# Usage

```php
//For Example:
use Zrny\MkSQL\Updater;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Table;

class Repository
{    
    public function __construct(PDO $pdo)
    {
        $Updater = new Updater($pdo);
    
        // Example of doing same structure
        // 1. We can create objects
    
        // Every table automatically gets "id" column that is auto increment.
        $Accounts = new Table("accounts");
    
        $Login = new Column("login", "varchar(60)");
        $Login->setUnique();
        $Login->setNotNull();
        $Accounts->columnAdd($Login);
    
        $Email = new Column("email", "varchar(60)");
        $Email->setUnique();
        $Email->setNotNull();
        $Accounts->columnAdd($Email);
    
        $Password = new Column("password", "varchar(255)");
        $Password->setNotNull();
        $Accounts->columnAdd($Password);
    
        $Updater->tableAdd($Accounts);
    
        $Sessions = new Table("sessions");
    
        $SessionID = new Column("session_id");
        $SessionID->setComment("SHA 128bit Key");
        $SessionID->setUnique();
        $SessionID->setNotNull();
        $Sessions->columnAdd($SessionID);
    
        $Account = new Column("account_id");
        $Account->addForeignKey("accounts.id");
        $Sessions->columnAdd($Account);
    
        $Updater->install();
    
        // 2. Or we can create whole structure fluently: (depends on your taste)
    
       $Updater->tableCreate("accounts")
    
            ->columnCreate("login","varchar(60)")
                ->setUnique()
                ->setNotNull()
            ->endColumn()
    
            ->columnCreate("email","varchar(60)")
                ->setUnique()
                ->setNotNull()
            ->endColumn()
    
            ->columnCreate("password", "varchar(255)")
                ->setNotNull()
            ->endColumn()
        ->endTable()
    
        ->tableCreate("roles")
            ->columnCreate("desc","char(60)")
            ->endColumn()
        ->endTable()
    
        ->tableCreate("sessions")
    
            ->columnCreate("session_id","char(32)")
                ->setComment("SHA 128bit Key")
                ->setUnique()
                ->setNotNull()
            ->endColumn()
    
            ->columnCreate("account") //Type is "int" by default
                ->addForeignKey("accounts.id")
            ->endColumn()
    
        ->endTable()
        ->install();
    }
}
```
    
### [Tracy](https://tracy.nette.org/en/) Panel

Add this to your bootstrap file:
```php
use \Zrny\MkSQL\Tracy\Panel;
Tracy\Debugger::getBar()->addPanel(new Panel());
````

Or, if you are using [Nette Framework](https://nette.org/en/), 
register it in your configuration file:

```neon
tracy: 
    bar: 
        - Zrny\MkSQL\Tracy\Panel
```


     
    
        





