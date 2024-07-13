<?php

declare(strict_types=1);

namespace App\Commands;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate-excel-file')]
class GenerateExcelFileCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->setDescription('Creates new Excel file with passed dimensions')
            ->addOption('filename', null, InputOption::VALUE_REQUIRED, 'Filename to save file')
            ->addOption('width', null, InputOption::VALUE_OPTIONAL, 'Number of columns', 100)
            ->addOption('height', null, InputOption::VALUE_OPTIONAL, 'Number of rows', 100);
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();

        $filename = $input->getOption('filename');
        if (!$filename) {
            $output->writeln("<error>The --filename option is required.</error>");
            return Command::FAILURE;
        }
        $columnHeader = Coordinate::stringFromColumnIndex(1000);
        $output->writeln("<info>Column header for 1000 column is: $columnHeader</info>");

        $width = $input->getOption('width');
        $height = $input->getOption('height');

        $output->writeln("Will create file: $filename with dimensions $width x $height");

        for ($row = 1; $row <= $height; $row++) {
            for ($col = 1; $col <= $width; $col++) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($col) . $row;
                $activeWorksheet->setCellValue($cellCoordinate, '$' . Coordinate::stringFromColumnIndex($col) . '$' . $row);
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filename);

        $output->writeln("Excel file created successfully: $filename");

        return self::SUCCESS;
    }
}