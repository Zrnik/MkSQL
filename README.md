# MkSQL

![GitHub](https://img.shields.io/github/license/zrnik/mksql)
![PHP Version](https://img.shields.io/packagist/php-v/zrnik/mksql)
![Packagist Downloads](https://img.shields.io/packagist/dm/zrnik/mksql)
![Packagist Version](https://img.shields.io/packagist/v/zrnik/mksql)

### What is it?
*So, I just found out that the thing I created 
is usually called an ORM.*

**MkSQL** is a tool for keeping your tables up to 
date with PHP code. It aims for a simple use cases,
so it cannot handle very complex stuff. Explore the 
docs to see what is possible.

This package simply allows you to define entities, 
that  represent your database tables, and automatically
creates them for you.

You can also skip the `ORM` part and use `Updater` class
to create your database without any entities, instead
of creating them with Adminer *(or PHPMyAdmin)*. 

Documentation index is in [docs/index.md](docs/index.md) file.

#### Requirements

This package **requires** you to run it with PHP 8+, as it uses 
the new stuff this version delivers. Mainly attributes and 
promoted constructor properties.

```json 
{
    "PHP": ">= 8",
    "ext-pdo": "*",

    "nette/utils": "^3.0",
    "zrnik/enum": "^1",
    
    "ext-iconv": "*",
    "ext-intl": "*"
}
```

#### Installation

`composer require zrnik/mksql`

Read more at [Installation and Configuration](docs/install-and-config.md) page.

#### Supported Drivers: 

- [✅ MySQL](https://www.mysql.com) (Compatible with MariaDB)
- [✅ SQLite 3](https://www.sqlite.org/index.html)
    
#### This package contains a [Tracy](https://tracy.nette.org/en/) panel

Add this to your bootstrap file:
```php
Tracy\Debugger::getBar()->addPanel(new \Zrnik\MkSQL\Tracy\Panel());
```

Or, if you are using [Nette Framework](https://nette.org/en/), 
register it in your configuration file:

```neon
tracy: 
    bar: 
        - Zrnik\MkSQL\Tracy\Panel
```
