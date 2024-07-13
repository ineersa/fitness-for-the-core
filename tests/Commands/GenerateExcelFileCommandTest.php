<?php

namespace Tests\Commands;

use App\Commands\GenerateExcelFileCommand;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GenerateExcelFileCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $command = new GenerateExcelFileCommand();
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $filename = __DIR__ . '/_files/test_excel_file.xlsx';
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }

    public function testExecute()
    {
        $filename = __DIR__ . '/_files/test_excel_file.xlsx';

        $this->commandTester->execute([
            '--filename' => $filename,
            '--width' => 10,
            '--height' => 10,
        ]);

        $this->assertFileExists($filename);

        $spreadsheet = IOFactory::load($filename);
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertEquals('$A$1', $worksheet->getCell('A1')->getValue());
        $this->assertEquals('$J$10', $worksheet->getCell('J10')->getValue());

        @unlink($filename);
    }

    public function testExecuteWithoutFilename()
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('The --filename option is required.', $output);
    }
}