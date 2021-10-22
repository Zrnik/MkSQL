<?php declare(strict_types=1);

namespace Examples\Accounts\Pure;

use Examples\Accounts\Installable\Account;
use PDO;

class AccountFactory
{

    public function __construct(private PDO $pdo)
    {
    }

    public function getAccountById(int $id): ?Account
    {
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
