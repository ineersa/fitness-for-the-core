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

#[AsCommand(name: 'load-data')]
class LoadDataIntoMySQL extends Command
{
    private OutputInterface $output;
    private int $startMemory = 0;
    private $startTime = 0;

    public function __construct(
        private readonly \PDO $pdo
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

        $matrixGenerator = new MatrixGenerator(1000, 1000);
        $sizes = [2000, 5000, 10000, 20000, 50000, 100000];
        foreach ($sizes as $size) {
            $this->resetTable();
            $this->batchInsert($matrixGenerator, $size);
        }

        $this->resetTable();
        $this->batchUpsert($matrixGenerator);

        $sizes = [1000, 2000, 5000, 10000];
        foreach ($sizes as $size) {
            $this->resetTable();
            $this->batchInsert($matrixGenerator, $size, true);
        }

        $this->resetTable();
        $this->loadDataFromCSV($matrixGenerator);

        return self::SUCCESS;
    }

    private function resetTable(): void
    {
        $this->output->writeln('<info>Reset table<info>');
        $this->pdo->exec("
            DROP TABLE IF EXISTS `exceltable`;
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS exceltable (
                `column` VARCHAR(4) NOT NULL,
                `row` INT NOT NULL,
                value VARCHAR(255) NOT NULL,
                PRIMARY KEY (`column`, `row`)
            )
        ");
        $this->output->writeln('<info>Reset table DONE<info>');
    }

    private function loadDataFromCSV(MatrixGenerator $matrixGenerator): void
    {
        $this->output->writeln('<info>Starting CSV generation and LOAD DATA INFILE process</info>');

        $csvFilename = tempnam('/data', 'mysql_import_');
        $totalRows = 0;

        try {
            $this->startTimeAndMemory();
            // Generate CSV file
            $csvFile = fopen($csvFilename, 'w');
            foreach ($matrixGenerator->getGenerator() as $cell) {
                fputcsv($csvFile, [$cell['column'], $cell['row'], $cell['value']]);
                $totalRows++;
            }
            fclose($csvFile);
            $this->logTimeAndMemory("CSV file generation");

            $this->output->writeln("<info>CSV file generated: $csvFilename</info>");

            // Load data into MySQL
            $query = <<<SQL
                LOAD DATA LOCAL INFILE :filename
                INTO TABLE exceltable
                FIELDS TERMINATED BY ',' 
                ENCLOSED BY '"'
                LINES TERMINATED BY '\n'
                (`column`, `row`, `value`)
            SQL;
            $this->startTimeAndMemory();
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':filename' => $csvFilename]);

            $this->logTimeAndMemory("LOAD DATA LOCAL INFILE completed. Total rows: $totalRows");
        } catch (\Exception $e) {
            $this->output->writeln("<error>Error during LOAD DATA INFILE: " . $e->getMessage() . "</error>");
        } finally {
            // Clean up the temporary CSV file
            if (file_exists($csvFilename)) {
                unlink($csvFilename);
            }
            $this->pdo->exec('SET GLOBAL local_infile=0;');
        }
    }

    private function batchInsert(MatrixGenerator $matrixGenerator, int $batchSize = 2000, bool $disableKeys = false): void
    {
        $this->startTimeAndMemory();
        $this->output->writeln('<info>Starting batch insert</info>');

        try {
            if ($disableKeys) {
                $this->output->writeln('<info>Disabling keys</info>');
                $this->pdo->exec('SET autocommit=0');
                $this->pdo->beginTransaction();
                $this->pdo->exec('ALTER TABLE exceltable DISABLE KEYS');
            }

            $totalRows = 0;
            $batches = 0;

            $sql = "INSERT INTO exceltable (`column`, `row`, `value`) VALUES ";
            $valuePlaceholders = [];
            $params = [];

            foreach ($matrixGenerator->getGenerator() as $cell) {
                $valuePlaceholders[] = "(?, ?, ?)";
                $params[] = $cell['column'];
                $params[] = $cell['row'];
                $params[] = $cell['value'];
                $totalRows++;

                // When we reach the batch size, execute the query
                if (count($valuePlaceholders) === $batchSize) {
                    $this->executeBatchInsert($sql, $valuePlaceholders, $params);
                    $valuePlaceholders = [];
                    $params = [];
                    $batches++;
                }

            }

            // Insert any remaining rows
            if (!empty($valuePlaceholders)) {
                $this->executeBatchInsert($sql, $valuePlaceholders, $params);
                $batches++;
            }

            if ($disableKeys) {
                $this->output->writeln('<info>Enabling keys</info>');
                $this->pdo->commit();
            }
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->output->writeln("<error>Error during batch insert: " . $e->getMessage() . "</error>");
        } finally {
            if ($disableKeys) {
                $this->pdo->exec('SET autocommit=1');
                $this->pdo->exec('ALTER TABLE exceltable ENABLE KEYS');
            }
        }

        $this->logTimeAndMemory("Batch insert completed. Total rows: $totalRows, Batches: $batches, BatchSize: $batchSize");
    }

    private function executeBatchInsert(string $sql, array $valuePlaceholders, array $params): void
    {
        $sql .= implode(', ', $valuePlaceholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function batchUpsert(MatrixGenerator $matrixGenerator, int $batchSize = 10000): void
    {
        $this->startTimeAndMemory();
        $this->output->writeln('<info>Starting batch upsert</info>');

        $totalRows = 0;
        $batches = 0;

        $sql = "INSERT INTO exceltable (`column`, `row`, `value`) VALUES ";
        $valuePlaceholders = [];
        $params = [];

        foreach ($matrixGenerator->getGenerator() as $cell) {
            $valuePlaceholders[] = "(?, ?, ?)";
            $params[] = $cell['column'];
            $params[] = $cell['row'];
            $params[] = $cell['value'];
            $totalRows++;

            // When we reach the batch size, execute the query
            if (count($valuePlaceholders) === $batchSize) {
                $this->executeBatchInsert($sql, $valuePlaceholders, $params);
                $valuePlaceholders = [];
                $params = [];
                $batches++;
            }

        }

        // Insert any remaining rows
        if (!empty($valuePlaceholders)) {
            $this->executeBatchUpsert($sql, $valuePlaceholders, $params);
            $batches++;
        }

        $this->logTimeAndMemory("Batch upsert completed. Total rows: $totalRows, Batches: $batches, BatchSize: $batchSize");
    }

    private function executeBatchUpsert(string $sql, array $valuePlaceholders, array $params): void
    {
        $sql .= implode(', ', $valuePlaceholders);
        $sql .= " ON DUPLICATE KEY IGNORE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}