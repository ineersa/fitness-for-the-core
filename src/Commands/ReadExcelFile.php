<?php

declare(strict_types=1);

namespace App\Commands;

use App\Helpers\MatrixGenerator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'read-excel-file')]
class ReadExcelFile extends Command
{
    private OutputInterface $output;
    private int $startMemory = 0;
    private $startTime = 0;

    public function __construct(
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Reads Excel file into database')
            ->addOption('filename', null, InputOption::VALUE_REQUIRED, 'Filename to save file')
            ;
    }

    protected function startTimeAndMemory(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    protected function logTimeAndMemory(string $message): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $this->startTime;
        $memoryUsage = $endMemory - $this->startMemory;

        $logMessage = sprintf(
            "%s, Execution time: %.4f seconds, Memory usage: %.2f MB",
            $message,
            $executionTime,
            $memoryUsage / 1024 / 1024
        );
        $this->output->writeln("<fire>$logMessage</>");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $outputStyle = new OutputFormatterStyle('cyan', '#000000', ['bold', 'blink']);
        $output->getFormatter()->setStyle('fire', $outputStyle);
        $filename = $input->getOption('filename');
        if (!$filename) {
            $output->writeln("<error>The --filename option is required.</error>");
            return Command::FAILURE;
        }

        $this->startTimeAndMemory();
        $spreadsheet = IOFactory::load($filename);
        $worksheet = $spreadsheet->getActiveSheet();
        $this->logTimeAndMemory("Loading xls into worksheet");
        $this->readToArray($worksheet);
        sleep(2);// to allow gc
        $this->readWithIterator($worksheet);
        sleep(1);
        $this->generateAndIterate();
        sleep(1);
        $this->generateData();

        return self::SUCCESS;
    }

    private function readToArray(Worksheet $worksheet): void
    {
        $this->output->writeln('Testing read of XLS file into array');

        $this->startTimeAndMemory();
        $dataArray = $worksheet->toArray();
        $t = null;
        foreach ($dataArray as $row) {
            foreach ($row as $cellValue) {
                $t = $cellValue;
            }
        }
        $this->logTimeAndMemory("Processing time to load xls to array");
    }

    private function readWithIterator(Worksheet $worksheet): void
    {
        $this->output->writeln('Testing read of XLS file with iterator');
        sleep(1);
        $this->startTimeAndMemory();
        $rowIterator = $worksheet->getRowIterator();
        $t = null;
        foreach ($rowIterator as $row) {
            $columnIterator = $row->getCellIterator();
            foreach ($columnIterator as $cell) {
                $t = $cell->getValue();
            }
        }
        $this->logTimeAndMemory("Processing time read of XLS file with iterator");
    }

    private function generateData(): array
    {
        $this->output->writeln('Testing with generator');
        sleep(1);
        $this->startTimeAndMemory();
        $generator = new MatrixGenerator(1000, 1000);

        $data = $generator->generate();
        $this->logTimeAndMemory("Processing time with generator and load to array");

        return $data;
    }

    private function generateAndIterate(): void
    {
        $this->output->writeln('Testing with generator and iterations');
        sleep(1);
        $this->startTimeAndMemory();
        $generator = new MatrixGenerator(1000, 1000);

        $t = null;
        foreach ($generator->getGenerator() as $cell) {
            $t = $cell['value']; // to emulate work
        }
        $this->logTimeAndMemory("Processing time with generator and iterations");
    }

}