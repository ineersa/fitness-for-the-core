<?php

declare(strict_types=1);

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MatrixGenerator
{
    public function __construct(
        private readonly int $rows,
        private readonly int $columns
    )
    {
    }

    public function generate(): array
    {
        $result = [];
        for ($i = 1; $i <= $this->rows; $i++) {
            for ($j = 1; $j <= $this->columns; $j++) {
                // don't see a point to rewrite that method
                $result[$i][$j] = '$' . Coordinate::stringFromColumnIndex($j) . '$' . $i;
            }
        }

        return $result;
    }

    public function getGenerator(): \Generator
    {
        for ($i = 1; $i <= $this->rows; $i++) {
            for ($j = 1; $j <= $this->columns; $j++) {
                yield [
                    'row' => $i,
                    'column' => Coordinate::stringFromColumnIndex($j),
                    'value' => '$' . Coordinate::stringFromColumnIndex($j) . '$' . $i
                ];
            }
        }
    }
}