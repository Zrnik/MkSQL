<?php declare(strict_types=1);

namespace Tests\Repository\Fetcher\FetcherMock;

use Tests\Repository\Fetcher\FetcherMock\Entities\Car;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class FetcherTestRepository extends Installable
{


    protected function install(Updater $updater): void
    {
        $updater->use(Car::class);
    }

    /**
     * @param int $id
     * @return Car|null
     */
    public function getCarById(int $id): ?Car
    {
        /**
         * @var Car $result
         * @noinspection PhpUnnecessaryLocalVariableInspection
         */
        $result = $this->getResultByKey(Car::class, 'id', $id);
        return $result;
    }
}