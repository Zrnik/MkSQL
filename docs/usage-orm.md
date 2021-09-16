# ORM Usage

This functionality is in MkSQL since v0.8. and I actually didn't know
what ORM meant before, as I never used any and didn't know I would
need it to stop writing a ton of `toArray` and `fromArray`/`fromRow` methods 
in entities. If I knew what it is and how to use for example `Doctrine`
I might actually never write this package. (But I have no idea if Doctrine 
can automatically create tables, maybe I could just write adapter for creating
tables for it?)

Anyway I would not use Doctrine in PHP lower than 8, because I believe
that comments (annotations) should not be part of the working code. That changed
with introduction of `Attributes`, so I think Doctrine is the way now.

So, let's look at the factory and entity from previous page introducing the 
`Updater` class for us in 
[Factory Usage & Installable Class](usage-factory-installable.md)
page. *You should probably read it, if you didn't yet.*

*AccountFactory.php file:*
```php
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

```

*Account.php file:*
```php
class Account
{
    final private function __construct(
        public int    $id,
        public string $username,
        public string $password,
    )
    {
    }

    /**
     * @param Traversable<mixed> $row
     * @return static
     */
    public static function fromRow(Traversable $row): static
    {
        $data = iterator_to_array($row);

        return new static(
            (int) $data['id'], (string) $data['username'], (string) $data['password'],
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

I promised you, that you can get rid of all those
methods, and I deliver!

Extend your entity with `BaseEntity` class! You can then safely 
remove `fromArray` and `toArray` methods! Look at this:

```php
#[TableName('account')]
class Account extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[NotNull]
    #[Unique]
    #[ColumnType('varchar(60)')]
    public string $username;

    #[NotNull]
    #[ColumnType('char(64)')]
    #[Comment('sha256')]
    public string $password;
}
```

can you see that it has everything from this below?

```php
    $account = $updater->tableCreate('account');
    
    $account->columnCreate('username', 'varchar(60)')
        ->setNotNull()->setUnique();
    
    $account->columnCreate('password', 'char(64)')
        ->setNotNull()->setComment('sha256');
```

That means, we don't need to write our updater anymore! 
As mentioned before, in the [Factory Usage & Installable Class](usage-factory-installable.md),
this allows us to change our `install` method to this:

```php
protected function install(Updater $updater): void
{
    $updater->use(Account::class);
}
```

Installable class is also extending `BaseRepository` class! More
about it on [Base Repository Usage](usage-orm.md) page.

To tease you a little and get ahead my self again, this is how the new
`AccountFactory.php` file looks:

```php
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
```

We are getting closer to the end of it all,
the last page awaiting your attention is
[Base Repository Usage](usage-orm.md)!
















