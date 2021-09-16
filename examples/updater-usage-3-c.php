<?php declare(strict_types=1);
use Zrnik\MkSQL\Updater;

include __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' .  __FILE__ . '.sqlite');
$updater = new Updater($pdo);

$multipleTableColumn = new \Zrnik\MkSQL\Column('createDate', 'int' /* 'int' is actually default value */);

$o = $updater->tableCreate('shop_order');
$p = $updater->tableCreate('payment');
$i = $updater->tableCreate('invoice');

$o->columnAdd($multipleTableColumn);
$p->columnAdd(clone $multipleTableColumn); //Cloning will create NEW instance of the column
$i->columnAdd(clone $multipleTableColumn); // and REMOVES the parent from it.

$updater->install();
