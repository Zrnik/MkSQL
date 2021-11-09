<?php declare(strict_types=1);

namespace Tests\Utilities\Sorting;

use Brick\DateTime\LocalDate;

class SortableClass
{
    public function __construct(
        public string $name,
        public string $text,
        public LocalDate $date,
    )
    { }

}
