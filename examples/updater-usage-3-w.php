<?php declare(strict_types=1);
use Zrnik\MkSQL\Updater;

include __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' .  __FILE__ . '.sqlite');
$updater = new Updater($pdo);

$multipleTableColumn = new \Zrnik\MkSQL\Column('createDate', 'int' /* 'int' is actually default value */);

$o = $updater->tableCreate('shop_order');
$p = $updater->tableCreate('payment');
$i = $updater->tableCreate('invoice');

$o->columnAdd($multipleTableColumn); // This is ok, and '$multipleTableColumn' will get '$o' table as parent.
$p->columnAdd($multipleTableColumn); // Error and the execution ends because '$multipleTableColumn' already has a parent
$i->columnAdd($multipleTableColumn); // You will not get here...

$updater->install();
