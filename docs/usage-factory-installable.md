# Factory usage & Installable class

There is definitely a class structure, that has a factory, which is
creating for example account objects, then you have the account class,
and probably some SQL file we must execute to install the table
and make everything working. For a "little" example, it might look like this:

*File: AccountFactory.php*
```php
class AccountFactory {

    public function __construct(private PDO $pdo)
    { }

    public function getAccountById(int $id): ?Account {
        // The PDO instance in installable class is 'protected' so
        // we can keep using '$this->pdo' here!
        $statement = $this->pdo->prepare('SELECT * FROM account WHERE id = :id');
        $statement->execute(['id' => $id]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        if($result === false) {
            return null;
        }
        return Account::fromArray(iterator_to_array($result));
    }

    public function saveAccount(Account $account): void {
        if($account->id === null) {
            $this->createAccount($account);
        }
        else {
            $this->updateAccount($account);
        }
    }

    private function createAccount(Account $account): void
    {
        $statement = $this->pdo->prepare('INSERT INTO account (username, password) VALUES (:username, :password)');

        $accountData = $account->toArray();
        unset($accountData['id']);
        $queryData = [];

        foreach($accountData as $key => $value) {
            $queryData[sprintf(':%s', $key)] = $value;
        }

        $statement->execute($queryData);
        $account->id = (int) $this->pdo->lastInsertId();
    }

    private function updateAccount(Account $account): void
    {
        $statement = $this->pdo->prepare('UPDATE account SET username=:username, password=:password WHERE id=:id');

        $accountData = $account->toArray();
        $queryData = [];

        foreach($accountData as $key => $value) {
            $queryData[sprintf(':%s', $key)] = $value;
        }

        $statement->execute($queryData);
    }
}
```
*File: Account.php*
```php
class Account
{
    final private function __construct(
        public ?int   $id,
        public string $username,
        public string $password,
    )
    {
    }

    /**
     * @param array<mixed> $data
     * @return static
     */
    #[Pure]
    public static function fromArray(array $data): static
    {
        return new static(
            $data['id'] ?? null, (string)$data['username'], (string)$data['password'],
        );
    }

    /**
     * @return array<mixed>
     */
    #[ArrayShape(['id' => 'int', 'username' => 'string', 'password' => 'string'])]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
```
*File: AccountTable.sql* (your database installation file)
```sql
CREATE TABLE `account` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(60) NOT NULL,
    `password` char(64) NOT NULL COMMENT 'sha256',
    PRIMARY KEY (`id`),
    UNIQUE KEY `account_username_unique_index` (`username`),
    UNIQUE KEY `account_id_unique_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3
```

That must be a pain to write so much stuff for each class 
we would like to create. And I am not even talking about a
case when you want to add new column to the account table,
writing an alter query to the sql file and editing all the
queries in save/fetch methods... Damn...

So what can **MkSQL** help you with?

## Use updater instead of the `.sql` file!

We can omit the `AccountTable.sql` entirely by using the updater. 
File `Account.php` will stay the same for now 
(more in the [ORM Usage](usage-orm.md) section),
but we will change `AccountFactory.php` like this:

```php
    private static bool $installed = false;

    public function __construct(private PDO $pdo)
    {
        $this->install();
    }

    private function install(): void
    {
        // If we create multiple 'AccountFactory' instances
        // (btw, you should not) we want it to be installed
        // only once.
        if(static::$installed) {
            return;
        }

        static::$installed = true;

        $updater = new Updater($this->pdo);

        $account = $updater->tableCreate('account');

        $account->columnCreate('username', 'varchar(60)')
            ->setNotNull()->setUnique();

        $account->columnCreate('password', 'char(64)')
            ->setNotNull()->setComment('sha256');

        $updater->install();
    }

    public function getAccountById(int $id): ?Account {
        // It's still the same as above
    }

    public function saveAccount(Account $account): void {
        // It's still the same as above
    }

    private function createAccount(Account $account): void
    {
        // It's still the same as above
    }

    private function updateAccount(Account $account): void
    {
        // It's still the same as above
    }
```

This helped two things, the table `account` is created automatically and
is also automatically updated when you change it. It means we don't need
to update database manually when we are updating our website. How about 
adding new column to the `account` table now? Much better on the database
side, but still pain.

Also, the code is still ugly and who would like to have additional property
(`$installed`) in the factory? That's where `Installable` class is coming 
to make our life little nicer...

New `AccountFactory` extending `Installable` class:

```php
class AccountFactory extends Installable {

    // Installable constructor wants 'PDO' itself, so we
    // don't even need the constructor now

    // Installable has this abstract method 'install'
    protected function install(Updater $updater): void
    {
        $account = $updater->tableCreate('account');

        $account->columnCreate('username', 'varchar(60)')
            ->setNotNull()->setUnique();

        $account->columnCreate('password', 'char(64)')
            ->setNotNull()->setComment('sha256');
    }

    public function getAccountById(int $id): ?Account {
        // It's still the same as above
    }

    public function saveAccount(Account $account): void {
        // It's still the same as above
    }

    private function createAccount(Account $account): void
    {
        // It's still the same as above
    }

    private function updateAccount(Account $account): void
    {
        // It's still the same as above
    }     
}
```

I mean, yeah, the installation of the database looks better, but still, when 
we want to add another column to `account` table, we still must create the 
property, modify `fromArray` and `toArray` methods, update `updateAccount`
and `createAccount` method and create the column in `install` method. That's 
still so much work, where you can make a mistake and spend even more time to
find bugs. That's where ORM comes in, how about we could throw away 
`getAccountById`, `saveAccount`, `createAccount`, `updateAccount` methods
and reduce our `install` method to this:

```php
    protected function install(Updater $updater): void
    {
        $updater->use(Account::class);
    }
```

But I am getting ahead of my self, lets continue with [ORM Usage](usage-orm.md)!








