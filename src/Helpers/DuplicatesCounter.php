<?php

declare(strict_types=1);

namespace App\Helpers;

class DuplicatesCounter
{
    public function __construct(private readonly MatrixGenerator $matrixGenerator)
    {
    }

    public function getCellsCountWithAdjacentDuplicates(): int
    {
        $count = 0;
        foreach ($this->matrixGenerator->getGenerator() as $cell) {
            if (strlen($cell['column']) < 2) {
                continue;
            }
            $prev = null;
            $letters = str_split($cell['column']);
            foreach ($letters as $letter) {
                if ($prev === null) {
                    $prev = $letter;
                } elseif ($prev !== $letter) {
                    $prev = $letter;
                } else {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }
}