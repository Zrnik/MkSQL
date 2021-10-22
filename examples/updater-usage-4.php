<?php declare(strict_types=1);

use Zrnik\MkSQL\Updater;

include __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' . __FILE__ . '.sqlite');
$updater = new Updater($pdo);

$a = $updater->tableCreate('accounts');

// Username must be set and must be unique
$a->columnCreate('username', 'varchar(60)')
    ->setNotNull()->setUnique();

// Passwords should not be null, and I guess they will be unique
// by design, so I don't care about unique index.
// We also comment what algo is used to generate it... (no effect in SQLite)
$a->columnCreate('password', 'char(64)')
    ->setNotNull()->setComment('sha256 value');

// You don't need to set 'administrator' value when creating a row,
// and we definitely want ordinary user NOT to be administrators!
$a->columnCreate('administrator', 'tinyint(1)')
    ->setNotNull()->setDefault(0);

$updater->install();
