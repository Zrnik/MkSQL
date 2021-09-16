<?php declare(strict_types=1);

/**
 * This file is just a test, that the factory and methods in it are working correctly...
 */

include __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' .  __FILE__ . '.sqlite');

$installableAccountFactory = new \Examples\Accounts\Installable\AccountFactory($pdo);

$newAccount = \Examples\Accounts\Installable\Account::fromArray(
    [
        'username' => 'qwe',
        'password' => 'asd',
    ]
);

$installableAccountFactory->saveAccount($newAccount);

$newAccount->username = 'poi';

$installableAccountFactory->saveAccount($newAccount);


