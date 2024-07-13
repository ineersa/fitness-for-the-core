# Test Excel file creation/parsing/storing

To run command you need to run `composer install` first.

```bash
docker compose run --rm composer install
```

## Creation of Excel file

Excel file can be created via command:
```
generate-excel-file [--filename FILENAME] [--width [WIDTH]] [--height [HEIGHT]]
```

Example of usage:

```
docker compose run --rm cli generate-excel-file --filename=./_files/test.xlsx
```

To get column header we can use

```php
$columnHeader = Coordinate::stringFromColumnIndex(1000);
$output->writeln("<info>Column header for 1000 column is: $columnHeader</info>");
# Column header for 1000 column is: ALL
```

**Column header for 1000 column is: ALL**

## Read Data From Excel file

So I've tested few methods to retrieve data for task, it can be tested via:
```bash
docker compose run --rm cli read-excel-file --filename=./_files/test1000.xlsx
```

And results are quite surprising:
```php
Loading xls into worksheet, Execution time: 10.3604 seconds, Memory usage: 491.73 MB
Testing read of XLS file into array
Processing time to load xls to array, Execution time: 1.5761 seconds, Memory usage: 19.71 MB
Testing read of XLS file with iterator
Processing time read of XLS file with iterator, Execution time: 0.8591 seconds, Memory usage: 0.04 MB
Testing with generator and iterations
Processing time with generator and iterations, Execution time: 0.1500 seconds, Memory usage: 0.00 MB
Testing with generator
Processing time with generator and load to array, Execution time: 0.0932 seconds, Memory usage: 52.18 MB
```
So the slowest operation is actually opening Excel file. 

Surprisingly using iterator was faster than loading whole sheet into array (maybe need to do more work inside loop?).

If we generate same data by hands, results are what we are expecting, loading into array is faster, but generators give us lower memory footprint.


## Load data into MySQL

To test data inserts for MySQL I've created command:
```bash
docker compose run --rm cli load-data
```

Good optimized variant and for the most cases it would be enough, old simple batch insert.
Here is results for different batch sizes:
```
Batch insert completed. Total rows: 1000000, Batches: 500, BatchSize: 2000, Execution time: 4.5751 seconds, Memory usage: 0.13 MB

Batch insert completed. Total rows: 1000000, Batches: 200, BatchSize: 5000, Execution time: 4.4419 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 100, BatchSize: 10000, Execution time: 4.4310 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 50, BatchSize: 20000, Execution time: 4.6379 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 20, BatchSize: 50000, Execution time: 4.8376 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 10, BatchSize: 100000, Execution time: 4.9534 seconds, Memory usage: 0.00 MB
```
So as we can see fastest time is for 10k batch size, so larger batches not always better, there are few reasons for that like max_allowed_packet size and so on.

Also we can test upsert, with ON DUPLICATE KEY IGNORE, which theoretically should be faster, but result is quite the same as batch insert: 
```
Batch upsert completed. Total rows: 1000000, Batches: 100, BatchSize: 10000, Execution time: 4.4341 seconds, Memory usage: 0.13 MB
```

Also we can disable AUTOCOMMIT, and disable keys (if it's possible, but actually can be dangerous). 
With this we get slightly better results:
```
Batch insert completed. Total rows: 1000000, Batches: 1000, BatchSize: 1000, Execution time: 4.1832 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 500, BatchSize: 2000, Execution time: 4.1192 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 200, BatchSize: 5000, Execution time: 4.2356 seconds, Memory usage: 0.00 MB

Batch insert completed. Total rows: 1000000, Batches: 100, BatchSize: 10000, Execution time: 4.3623 seconds, Memory usage: 0.00 MB

```

Here 2k batch size gives best results.

If you have admin privileges or can use dumps, you can utilize mysqldump or LOAD DATA INFILE, which would be the fastest way.

```
Starting CSV generation and LOAD DATA INFILE process
CSV file generation, Execution time: 1.2835 seconds, Memory usage: 0.00 MB
CSV file generated: /data/mysql_import_AHiufV
LOAD DATA LOCAL INFILE completed. Total rows: 1000000, Execution time: 2.8261 seconds, Memory usage: 0.00 MB
```

As we can see `2.8261` is quite faster than anything we did before, but if we combine it with CSV create time it will be on par with bulk insert with disabled keys

```
A bonus question 1: find the count of cells where the "column" has a duplicated letter. For example, "AA",
"ABBD", "CCC" qualify. But "ABA" doesn't qualify because the As are not adjacent.
```
We can calculate it manually like this:
 - until 676 we will get doubles and there will be 26 duplicate columns
 - we will go AAB, AAC ...., AAZ further, so will be 52 duplicate columns until 728
 - then we will get 1 duplicate per 26, so 272/26~= 10.4, but we know that we end up on ALL, so will get 11
So the answer is 63*1000 = 63000.

But also we can easily calculate those, you can try test for this:
```bash
docker compose run --rm tests --filter=DuplicatesCounterTest
```

```
A bonus question 2: what are the cons of OOP? Provide real life examples.
```

>You can solve every problem with another level of indirection, except for the problem of too many levels of indirection 

This can bring a lot of complexity into application, for example I've worked on 1 project on 1st Zend, where some Grid classes were used for datatables, with 12 levels of abstraction.
It would take me literally half of a day to find a class and method which actually executes.

Of course, it will hit performance also, since everything comes with a cost. 

You can make really great design patterns using OOP, but sometimes it can cause overengineering, misuse of patterns and tight coupling. 

In some cases like in Java for example, you would need to write a lot more code, then in some structural language (Golang for example)
