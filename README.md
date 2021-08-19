# MkSQL

### Warning!
**You are currently looking at *v0.7* documentation.**
**There is not yet documentation for *v0.8*.**
**For v0.8 and more you will need PHP 8+**

/end warning

![GitHub](https://img.shields.io/github/license/zrnik/mksql)
![Packagist Downloads](https://img.shields.io/packagist/dm/zrnik/mksql)
![Packagist Version](https://img.shields.io/packagist/v/zrnik/mksql)  

**MkSQL** is a tool for keeping your tables up to date with PHP code. 
You can use it in your project or as a database preparation in 
your integration tests.

It's a good tool for prototyping, so you can just change your code instead
of fiddling with Adminer *(or PHPMyAdmin)*. I would not use it in production 
to save precious resources on runtime. Better way is creating a standalone 
script to run table update once when you upgrade and/or install your 
application.

Documentation index is in [docs/index.md](docs/index.md)

#### Requirements
```json 
{
    "PHP": ">= 8",
    "ext-pdo": "*",

    "nette/utils": "^3.0",
    "ext-iconv": "*",
    "ext-intl": "*",

    "zrnik/enum": "^1"
}
```

#### Installation

`composer require zrnik/mksql`

#### Supported Drivers: 

- [✅ MySQL](https://www.mysql.com)

- [✅ SQLite 3](https://www.sqlite.org/index.html) 

#### Supported Features: 

This library only supports basic features for creating the tables.

##### Column:

- Name
- Type
- `NULL` / `NOT NULL`
- `DEFAULT`
- `COMMENT` *(Not in SQLite)*
- `UNIQUE INDEX`
- `FOREIGN KEY`

##### Table:

- Name
- `PRIMARY KEY` (defined automatically, see [docs/usage.md](docs/usage.md))

#### Usage: 

Example: 
```php
use \Zrnik\MkSQL\Updater;

$pdo = new PDO("sqlite::memory:");
$updater = new Updater($pdo);

// Articles:
$articles = $updater->tableCreate("articles");

$articles->columnCreate("title","varchar(255)");

$articles->columnCreate("url_slug","varchar(255)")
    ->setNotNull()->setUnique();

$articles->columnCreate("content","longtext");

$articles->columnCreate("display_count","longtext")
    ->setNotNull()->setDefault(0);

// Comments:
$comments = $updater->tableCreate("comments");

$comments->columnCreate("author_name");

$comments->columnCreate("author_email");

$comments->columnCreate("article")
    ->addForeignKey("articles.id");

$comments->columnCreate("comment_text");

//Then we install it:
$updater->install();
```

For all the possible examples see [docs/usage.md](docs/usage.md).


    
#### [Tracy](https://tracy.nette.org/en/) Panel

Add this to your bootstrap file:
```php
use \Zrnik\MkSQL\Tracy\Panel;
Tracy\Debugger::getBar()->addPanel(new Panel());
```

Or, if you are using [Nette Framework](https://nette.org/en/), 
register it in your configuration file:

```neon
tracy: 
    bar: 
        - Zrnik\MkSQL\Tracy\Panel
```
