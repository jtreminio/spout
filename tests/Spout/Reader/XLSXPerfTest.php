<?php

namespace Box\Spout\Reader;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;

/**
 * Class XLSXPerfTest
 * Performance tests for XLSX Reader
 *
 * @package Box\Spout\Reader
 */
class XLSXPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * @return array
     */
    public function dataProviderForTestPerfWhenReadingTwoMillionRowsXLSX()
    {
        return [
            [$shouldUseInlineStrings = true, $expectedMaxExecutionTime = 1800], // seconds (30 minutes)
            [$shouldUseInlineStrings = false, $expectedMaxExecutionTime = 3600], // seconds (60 minutes)
        ];
    }

    /**
     * 2 million rows (each row containing 3 cells) should be read
     * in less than 20 minutes for inline strings, 40 minutes for
     * shared strings and the execution should not require
     * more than 10MB of memory
     *
     * @dataProvider dataProviderForTestPerfWhenReadingTwoMillionRowsXLSX
     * @group perf-test
     *
     * @param bool $shouldUseInlineStrings
     * @param int $expectedMaxExecutionTime
     * @return void
     */
    public function testPerfWhenReadingTwoMillionRowsXLSX($shouldUseInlineStrings, $expectedMaxExecutionTime)
    {
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = ($shouldUseInlineStrings) ? 'xlsx_with_two_million_rows_and_inline_strings.xlsx' : 'xlsx_with_two_million_rows_and_shared_strings.xlsx';
        $resourcePath = $this->getResourcePath($fileName);

        $reader = ReaderFactory::create(Type::XLSX);
        $reader->open($resourcePath);

        $numReadRows = 0;
        while ($reader->hasNextSheet()) {
            $reader->nextSheet();

            while ($reader->hasNextRow()) {
                $reader->nextRow();
                $numReadRows++;
            }
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
