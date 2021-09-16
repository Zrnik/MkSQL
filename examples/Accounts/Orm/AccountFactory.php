<?php declare(strict_types=1);

namespace Examples\Accounts\Orm;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class AccountFactory extends Installable
{
    protected function install(Updater $updater): void
    {
        $updater->use(Account::class);
    }

    public function getAccountById(int $id): ?Account
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @var Account|null $accountOrNull */
        $accountOrNull = $this->getResultByKey(Account::class, 'id', $id);
        return $accountOrNull;
    }

    public function saveAccount(Account $account): void
    {
        $this->save($account);
    }
}
