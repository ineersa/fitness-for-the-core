#!/usr/bin/env php
<?php
ini_set('memory_limit', -1);
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();

// Parse the DATABASE_URL
$dbParams = parse_url($_ENV['DATABASE_URL']);

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        $dbParams['host'],
        $dbParams['port'] ?? 3306,
        ltrim($dbParams['path'], '/')
    ),
    $dbParams['user'],
    $dbParams['pass'],
    [
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        PDO::MYSQL_ATTR_LOCAL_INFILE_DIRECTORY => '/data',
    ]
);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$application->add(new \App\Commands\GenerateExcelFileCommand());
$application->add(new \App\Commands\ReadExcelFile());
$application->add(new \App\Commands\LoadDataIntoMySQL($pdo));

$application->run();