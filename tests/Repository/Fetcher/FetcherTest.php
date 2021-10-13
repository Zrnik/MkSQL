<?php declare(strict_types=1);

namespace Tests\Repository\Fetcher;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Repository\Fetcher\FetcherMock\Entities\Car;
use Tests\Repository\Fetcher\FetcherMock\Entities\Manufacturer;
use Tests\Repository\Fetcher\FetcherMock\Entities\Part;
use Tests\Repository\Fetcher\FetcherMock\FetcherTestRepository;
use Zrnik\PHPUnit\Exceptions;

class FetcherTest extends TestCase
{
    use Exceptions;

    private static ?FetcherTestRepository $repository = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$repository = new FetcherTestRepository(
            new PDO('sqlite::memory:')
        );
    }

    /**
     * This test was created for continual testing when I created
     * 'Fetcher' class...
     *
     * It was called with 'composer phpunit:fetcher'
     * > phpunit --testsuite fetcher
     *
     * Now it's just a part of the full testing suite...
     */
    public function testFetcher(): void
    {
        static::assertNotNull(static::$repository);

        $tesla = Manufacturer::create();
        $tesla->name = 'Tesla';

        static::$repository->save($tesla);

        $manufacturer = Manufacturer::create();
        $manufacturer->name = 'Cheng Car';

        $car1 = Car::create();
        $car1->name = 'Car 1';
        $this->addPart($car1, 'engine');
        $this->addPart($car1, 'gear');
        $this->addPart($car1, 'wheels');
        $manufacturer->cars[] = $car1;

        $car2 = Car::create();
        $car2->name = 'Car 2';
        $this->addPart($car2, 'engine');
        $this->addPart($car2, 'gear');
        $this->addPart($car2, 'wheels');
        $manufacturer->cars[] = $car2;

        static::$repository->save($manufacturer);

        $car1Fetched = static::$repository->getCarById($car1->id ?? -1);

        static::assertNotNull($car1Fetched);
        static::assertSame($car1->id, $car1Fetched->id);

        $car2Fetched = static::$repository->getCarById($car2->id ?? -1);

        static::assertNotNull($car2Fetched);
        static::assertSame($car2->id, $car2Fetched->id);
    }

    private function addPart(Car $car, string $partName): void
    {
        $newPart = Part::create();
        $newPart->car = $car;
        $newPart->name = $partName;
        $car->parts[] = $newPart;
    }
}
