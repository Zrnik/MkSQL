<?php declare(strict_types=1);

use Zrnik\MkSQL\Updater;

include __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' .  __FILE__ . '.sqlite');
$updater = new Updater($pdo);

$a = $updater->tableCreate('accounts');
$a->setPrimaryKeyName('uuid');
$a->setPrimaryKeyType('char(36)');
$a->columnCreate('username', 'varchar(60)');
$a->columnCreate('password', 'char(64)'); // sha256 result

$updater->install();
