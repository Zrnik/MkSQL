<?php declare(strict_types=1);

namespace Examples\Accounts\Updater;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

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
