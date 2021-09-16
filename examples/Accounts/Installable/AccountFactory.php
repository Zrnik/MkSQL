<?php declare(strict_types=1);

namespace Examples\Accounts\Installable;

use PDO;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class AccountFactory extends Installable
{

    // Installable constructor wants 'PDO' itself, so we
    // don't even need the constructor now.

    // Installable has this abstract method 'install'
    protected function install(Updater $updater): void
    {
        $account = $updater->tableCreate('account');

        $account->columnCreate('username', 'varchar(60)')
            ->setNotNull()->setUnique();

        $account->columnCreate('password', 'char(64)')
            ->setNotNull()->setComment('sha256');
    }

    public function getAccountById(int $id): ?Account
    {
        // The PDO instance in installable class is 'protected' so
        // we can keep using '$this->pdo' here!
        $statement = $this->pdo->prepare('SELECT * FROM account WHERE id = :id');
        $statement->execute(['id' => $id]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }
        return Account::fromArray(iterator_to_array($result));
    }

    public function saveAccount(Account $account): void
    {
        if ($account->id === null) {
            $this->createAccount($account);
        } else {
            $this->updateAccount($account);
        }
    }

    private function createAccount(Account $account): void
    {
        $statement = $this->pdo->prepare('INSERT INTO account (username, password) VALUES (:username, :password)');

        $accountData = $account->toArray();
        unset($accountData['id']);
        $queryData = [];

        foreach ($accountData as $key => $value) {
            $queryData[sprintf(':%s', $key)] = $value;
        }

        $statement->execute($queryData);
        $account->id = (int)$this->pdo->lastInsertId();
    }

    private function updateAccount(Account $account): void
    {
        $statement = $this->pdo->prepare('UPDATE account SET username=:username, password=:password WHERE id=:id');

        $accountData = $account->toArray();
        $queryData = [];

        foreach ($accountData as $key => $value) {
            $queryData[sprintf(':%s', $key)] = $value;
        }

        $statement->execute($queryData);
    }
}
