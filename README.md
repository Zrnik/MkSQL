# MkSQL 

**<span style="color:red;">Warning: </span> this package has no tests!**

This package is AutoUpdater for your MySQL database tables.

You define tables in the code, and the table structure will be Up2Date.

Currently only suported drivers are:
- mysql

Planned drivers to implement:
- sqlite 

This package has [nette/database ^3.0.6](https://github.com/nette/database) as a dependency and requires `PHP >=7.1` with `ext-pdo` installed!


# Initialization

I prefer to initialize tables in method called from constructor that gets **MkSQL** instance by DependencyInjection.

#### Example initialization in `ArticleRepository.php`
 
```php  
    public function __construct(MkSQL $MkSQL)
    {
        $this->installDatabase($MkSQL);
    }

    private function installDatabase(MkSQL $MkSQL)
    {
        $MkSQL->table("articles")
            ...
        ->install();
    }
```

#### Example configuration in `common.neon` in Nette Framework 

 We need to define `Zrny\MkSQL\Updater` in services. 
 The class requires DSN, because I use `Nette\Database\Connection`, 
 but I want this to work outside of `Nette Framework` so I need to 
 somehow get database credentials.
 
 We can do it like this:

```neon  
// configuration for default database
database:
	dsn: 'mysql:host=localhost;dbname=test'
	user: 'root'
	password: ''

// MkSQL service for DI
services:
    mksql: \Zrny\MkSQL\Updater('mysql:host=localhost;dbname=test','root','')
```

OR, as I do it, we define credentials in parameters, 
so we can edit them in one place when changed,
 or when you have different one in `local.neon`.

```neon  
// Credentials defined in parameters
parameters:
    database:
        dsn: 'mysql:host=localhost;dbname=test'
        user: 'root'
        password: ''

database:
	dsn: '%database.dsn%'
	user: '%database.user%'
	password: '%database.password%'

services:
    mksql: \Zrny\MkSQL\Updater('%database.dsn%','%database.user%','%database.password%')
```



#### Example manual initialization

You simply create an instance with credentials
in arguments same like `PDO` arguments.


```php  
    $MkSQL = new \Zrny\MkSQL\Updater('mysql:host=localhost;dbname=test','root','');
    $MkSQL->table("articles")
    ...
    ->install();
```

 
# Usage

If you think that class `\Zrny\MkSQL\Column` is missing `->setPrimary()` method (to create primary key),
then notice that every table created with MkSQL have primary key `id` 
that is `int` & `AUTO INCREMENT` by default and this behavior cannot be changed. 

Trying to define column `id` wil result in error.

```php  
    //Define:
    $updater->table("accounts")
        ->column("login","char(30)")
            ->setUnique()
            ->setNotNull()
        ->endColumn()
        ->column("password","char(60)")
            ->setComment("password from composer package")
            ->setNotNull()
        ->endColumn()
        ->column("roles","char(60)")
            ->setNotNull()
            ->setDefault(json_encode(["user"]))
        ->endColumn()
    ->endTable()->table("tokens")
        ->column("account")
            ->setForeign("accounts.id")
        ->endColumn()
    ->endTable();

    //Install
    $updater->install();
```