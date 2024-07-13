<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Helpers\MatrixGenerator;
use PHPUnit\Framework\TestCase;

class MatrixGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $generator = new MatrixGenerator(10, 10);

        $result = $generator->generate();

        $this->assertEquals('$A$1', $result[1][1]);
        $this->assertEquals('$B$1', $result[1][2]);
        $this->assertEquals('$A$2', $result[2][1]);
        $this->assertEquals('$J$10', $result[10][10]);
    }
}