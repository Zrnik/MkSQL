<?php declare(strict_types=1);

namespace Tests\Utilities\Sorting;

use Brick\DateTime\LocalDate;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Utilities\Sorting\Sorting;

class SortingTest extends TestCase
{
    public function testSorting(): void
    {
        $sortable1 = new SortableClass(
            'First', 'y', LocalDate::of(2002,1,1)
        );

        $sortable2 = new SortableClass(
            'Second', 'z', LocalDate::of(2002,1,1)
        );

        $sortable3 = new SortableClass(
            'Third', 'z', LocalDate::of(2001,1,2)
        );

        $sortable4 = new SortableClass(
            'Third', 'z', LocalDate::of(2000,1,2)
        );

        $sorted = Sorting::sortObjectsByProperty(
            [$sortable3, $sortable2, $sortable1], 'name'
        );

        static::assertSame([$sortable1, $sortable2, $sortable3], $sorted);

        $sorted = Sorting::sortObjectsByProperty(
            [$sortable2, $sortable1, $sortable3], 'text'
        );

        static::assertSame([$sortable1, $sortable2, $sortable3], $sorted);

        $sorted = Sorting::sortObjectsByProperty(
            [$sortable3, $sortable1, $sortable2, $sortable4], 'text'
        );

        static::assertSame([$sortable1, $sortable3, $sortable2, $sortable4], $sorted);

        $sorted = Sorting::sortObjectsByProperty(
            [$sortable1, $sortable2, $sortable3, $sortable4], 'date'
        );

        static::assertSame([$sortable4, $sortable3, $sortable1, $sortable2], $sorted);
    }
}
