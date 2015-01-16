<?php

namespace Box\Spout\Reader;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;

/**
 * Class CSVPerfTest
 * Performance tests for CSV Reader
 *
 * @package Box\Spout\Reader
 */
class CSVPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * 2 million rows (each row containing 3 cells) should be read
     * in less than 150 seconds and the execution should not require
     * more than 10MB of memory
     *
     * @group perf-test
     *
     * @return void
     */
    public function testPerfWhenReadingTwoMillionRowsCSV()
    {
        $expectedMaxExecutionTime = 150; // seconds (2.5 minutes)
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = 'csv_with_two_million_rows.csv';
        $resourcePath = $this->getResourcePath($fileName);

        $reader = ReaderFactory::create(Type::CSV);
        $reader->open($resourcePath);

        $numReadRows = 0;
        while ($reader->hasNextRow()) {
            $reader->nextRow();
            $numReadRows++;
        }

        $reader->close();

        $expectedNumRows = 2000000;
        $this->assertEquals($expectedNumRows, $numReadRows, "$expectedNumRows rows should have been read");

        $executionTime = time() - $startTime;
        $this->assertTrue($executionTime < $expectedMaxExecutionTime, "Reading 2 million rows should take less than $expectedMaxExecutionTime seconds (took $executionTime seconds)");

        $memoryPeakUsage = memory_get_peak_usage(true);
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Reading 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . ($memoryPeakUsage / 1024 / 1024) . ' MB)');
    }
}
