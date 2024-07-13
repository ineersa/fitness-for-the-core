<?php

declare(strict_types=1);

namespace Helpers;

use App\Helpers\DuplicatesCounter;
use App\Helpers\MatrixGenerator;
use PHPUnit\Framework\TestCase;

class DuplicatesCounterTest extends TestCase
{
    public function testGetCellsCountWithAdjacentDuplicates(): void
    {
        $generator = new MatrixGenerator(2, 27);
        $duplicates = new DuplicatesCounter($generator);
        $this->assertEquals(2, $duplicates->getCellsCountWithAdjacentDuplicates());

        $generator = new MatrixGenerator(2, 28);
        $duplicates = new DuplicatesCounter($generator);
        $this->assertEquals(2, $duplicates->getCellsCountWithAdjacentDuplicates());

        $generator = new MatrixGenerator(2, 28);
        $duplicates = new DuplicatesCounter($generator);
        $this->assertEquals(2, $duplicates->getCellsCountWithAdjacentDuplicates());

        // AA + BB
        $generator = new MatrixGenerator(2, 54);
        $duplicates = new DuplicatesCounter($generator);
        $this->assertEquals(4, $duplicates->getCellsCountWithAdjacentDuplicates());

        $generator = new MatrixGenerator(1, 1000);
        $duplicates = new DuplicatesCounter($generator);
        $this->assertEquals(63, $duplicates->getCellsCountWithAdjacentDuplicates());

        $generator = new MatrixGenerator(1000, 1000);
        $duplicates = new DuplicatesCounter($generator);
        $this->assertEquals(63000, $duplicates->getCellsCountWithAdjacentDuplicates());
    }
}