<?php declare(strict_types=1);

use Zrnik\MkSQL\Updater;

include __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' . __FILE__ . '.sqlite');
$updater = new Updater($pdo);

$a = $updater->tableCreate('accounts');
$a->columnCreate('username', 'varchar(60)');
$a->columnCreate('password', 'char(64)');

$t = $updater->tableCreate('auth_token');
$t->columnCreate('account')
    ->addForeignKey('accounts.id');

// Or you can (and should) use properties of the table we are aiming at:
$t->columnCreate('account2', $a->getPrimaryKeyType())
    ->addForeignKey(sprintf('%s.%s', $a->getName(), $a->getPrimaryKeyName()));

// this can be achieved with 'addForeignTable' method:
$t->columnCreateForeign('account3', $a);

$t->columnCreate('token', 'varchar')
    ->setUnique();

$updater->install();
