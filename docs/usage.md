# Usage

You can use this in three ways: fluently, object way or hybrid way. 
Examples of those usages are after `Usage in Model or Factory` section.

On the end of this document you can see ALL the possible settings
we can define. **Please** **read** that section **intro** as it contains
an **important information** about `PRIMARY KEY` and **cloning columns**!
 
### Usage in Model or Factory

If you are using this in your project you should not 
call `Zrnik\MkSQL\Updater::intall();` method more than 
once per table, per runtime to save resources. This is only recommendation, 
it's not a mandatory thing. 

Example:

```php
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Updater;

class ExampleFactoryOrRepository {

    public function __construct(PDO $pdo)
    {
        $this->install($pdo);
    }

    private static bool $_dbInstalled = false;

    /**
     * @param PDO $pdo
     * @throws InvalidDriverException
     */
    private function install(PDO $pdo)
    {
        if(static::$_dbInstalled)
            return;
        
        static::$_dbInstalled = true;
        
        $updater = new Updater($pdo);

        // ... Here we define tables with columns        
        
        $updater->install();
    }

    // ... Here is the rest of your class
    
}
```

**Update:** As I am tired of writing a boolean static variable everytime, 
I created class `Zrnik\MkSQL\Utilities\Installable`. Example above can be 
now written like this:

```php
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class ExampleFactoryOrRepository extends Installable
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * This method is now required by `Installable` class
     * 
     * DO NOT CALL $updater->install(); as the Installable
     * parent will handle it for you.
     */
    public function install(Updater $updater): void
    {
        
        // ... Here we define tables with columns
        
    }

// ... Here is the rest of your class

}
```

Nice, isn't it?


### Hybrid Way, AKA: the good

I am starting this example section with 'Hybrid Way' because it's in 
my opinion the best one to use. Please notice the information 
about `PRIMARY KEY` at the end of this document.

```php
use Zrnik\MkSQL\Updater;

$pdo = new PDO("sqlite::memory:");
$updater = new Updater($pdo);

$account = $updater->tableCreate("account");

$account->columnCreate("username", "varchar(60)")
    ->setUnique(); // 'setUnique' automatically sets 'notNull' too!

$account->columnCreate("password", "varchar(255)");

$articles = $updater->tableCreate("articles");

// Undefined type is automatically 'int'
// please see document 'primary-key.md'!
$articles->columnCreate("author")
    ->addForeignKey("account.id");

$articles->columnCreate("title", "varchar(255)")
    ->setNotNull();

$articles->columnCreate("content", "longtext");

$updater->install();
```

### Fluent Way, AKA: the (not that) bad

Same example as above, but in the fluent way. Choice depends on your 
taste, but I consider it less readable. This example is here only 
to show that it is possible.

```php
use Zrnik\MkSQL\Updater;

$pdo = new PDO("sqlite::memory:");

(new Updater($pdo))

    ->tableCreate("account")
        
        ->columnCreate("username", "varchar(60)")
            ->setUnique()
        ->endColumn()
        
        ->columnCreate("password", "varchar(255)")
        ->endColumn()
    
    ->endTable()
    
    ->tableCreate("articles")

        ->columnCreate("author")
            ->addForeignKey("account.id")
        ->endColumn()

        ->columnCreate("title", "varchar(255)")
            ->setNotNull()
        ->endColumn()
        
        ->columnCreate("content", "longtext")
        ->endColumn()
    
    ->endTable()

->install();
```

### Object Way, AKA: the ugly

This is the ugliest way of making tables, but again, it's possible. 

```php
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

$pdo = new PDO("sqlite::memory:");

$updater = new Updater($pdo);

$accountUsername = new Column("username", "varchar(60)");
$accountUsername->setUnique();

$accountPassword = new Column("password", "varchar(255)");

$accounts = new Table("account");

$updater->tableAdd($accounts);

$accounts->columnAdd($accountUsername);
$accounts->columnAdd($accountPassword);

$articles = new Table("articles");

$articleAuthor = new Column("author");
$articleAuthor->addForeignKey("account.id");

$articles->columnAdd($articleAuthor);

$articleTitle = new Column("title", "varchar(255)");
$articleTitle->setNotNull();

$articles->columnAdd($articleTitle);

$articleContent = new Column("content", "longtext");

$articles->columnAdd($articleContent);

$updater->install();
```

### All the possible settings:

**Important:** Every table is getting a `PRIMARY KEY` **automatically**! 
This means that this package **does not** support **composite primary keys**.
The key is `id` by default, but can be changed by `\Zrnik\MkSQL\Table::setPrimaryKeyName()` method.

**Important #2:** If you want to define a column and add it to multiple
tables, you need to clone it. Column has a `parent` property, that is 
required for a column and once set, it cannot be rewritten. To add the 
same column to another table you need to clone it. Example of cloning is 
also shown in the example below.

```php
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Updater;

$pdo = new PDO("sqlite::memory:");

$updater = new Updater($pdo);

//This is column for cloning example.
$sortColumn = new Column("data_grid_sort_key");

//This is an example of 'setPrimaryKeyName'
$Accounts = $updater->tableCreate("accounts")->setPrimaryKeyName("account_id");

$Accounts->columnCreate("account_username","varchar(100)")
    ->setUnique();

$Accounts->columnCreate("account_password","char(64)")
    ->setNotNull()
    ->setComment("SHA256 Hash");

$Accounts->columnCreate("account_banned","tinyint") // or bool if possible?
->setNotNull()
    ->setDefault(0); // or false if bool?

//In the first "columnAdd" is "clone" redundant
$Accounts->columnAdd(clone $sortColumn);

$Sessions = $updater->tableCreate("sessions");

$Sessions->columnCreate("account_id")
    ->addForeignKey("accounts.account_id");

$Sessions->columnCreate("token", "varchar(100)");

// As column already has a parent "accounts", we need to clone it
// (cloning will create new instance without parent)
$Sessions->columnAdd(clone $sortColumn);
```

And that's everything about usage of this package. You can return to [index](index.md).




