<?php declare(strict_types=1);

namespace Tests\Mock\Repository;

use Exception;
use stdClass;
use Zrnik\MkSQL\Repository\CustomTypeConverter;

class HookExampleConverter extends CustomTypeConverter
{
    public ?stdClass $configuration = null;

    public function serialize(mixed $value): string|bool
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $encoded = json_encode($value);

        if($this->configuration === null) {
            throw new ConfigurationNotFoundException('Configuration not found!');
        }


        if($encoded === false) {
            throw new Exception('Should not be false!');
        }

        return $encoded;
    }

    public function deserialize(mixed $value): mixed
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $decoded = json_decode($value, true);

        if($this->configuration === null) {
            throw new ConfigurationNotFoundException('Configuration not found!');
        }

        return $decoded;
    }

    public function getDatabaseType(): string
    {
        return 'longtext';
    }
}
