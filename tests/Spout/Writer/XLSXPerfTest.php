<?php

namespace Box\Spout\Writer;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;
use Box\Spout\Writer\Internal\XLSX\Workbook;

/**
 * Class XLSXPerfTest
 * Performance tests for XLSX Writer
 *
 * @package Box\Spout\Writer
 */
class XLSXPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * @return array
     */
    public function dataProviderForTestPerfWhenWritingTwoMillionRowsXLSX()
    {
        return [
            [$shouldUseInlineStrings = true],
            [$shouldUseInlineStrings = false],
        ];
    }

    /**
     * 2 million rows (each row containing 3 cells) should be written
     * in less than 10 minutes and the execution should not require
     * more than 10MB of memory
     *
     * @dataProvider dataProviderForTestPerfWhenWritingTwoMillionRowsXLSX
     * @group perf-test
     *
     * @param bool $shouldUseInlineStrings
     * @return void
     */
    public function testPerfWhenWritingTwoMillionRowsXLSX($shouldUseInlineStrings)
    {
        $numRows = 2000000;
        $expectedMaxExecutionTime = 600; // seconds (10 minutes)
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = ($shouldUseInlineStrings) ? 'xlsx_with_two_million_rows_and_inline_strings.xlsx' : 'xlsx_with_two_million_rows_and_shared_strings.xlsx';
        $this->createGeneratedFolderIfNeeded($fileName);
        $resourcePath = $this->getGeneratedResourcePath($fileName);

        $writer = WriterFactory::create(Type::XLSX);
        $writer->setShouldUseInlineStrings($shouldUseInlineStrings);
        $writer->setShouldCreateNewSheetsAutomatically(true);

        $writer->openToFile($resourcePath);

        for ($i = 1; $i <= $numRows; $i++) {
            $writer->addRow(["xlsx--{$i}-1", "xlsx--{$i}-2", "xlsx--{$i}-3"]);
        }

        $writer->close();

        if ($shouldUseInlineStrings) {
            $numSheets = count($writer->getSheets());
            $this->assertEquals($numRows, $this->getNumWrittenRowsUsingInlineStrings($resourcePath, $numSheets), "The created XLSX ($fileName) should contain $numRows rows");
        } else {
            $this->assertEquals($numRows, $this->getNumWrittenRowsUsingSharedStrings($resourcePath), "The created XLSX ($fileName) should contain $numRows rows");
        }

        $executionTime = time() - $startTime;
        $this->assertTrue($executionTime < $expectedMaxExecutionTime, "Writing 2 million rows should take less than $expectedMaxExecutionTime seconds (took $executionTime seconds)");

        $memoryPeakUsage = memory_get_peak_usage(true);
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Writing 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . ($memoryPeakUsage / 1024 / 1024) . ' MB)');
    }

    /**
     * @param string $resourcePath
     * @param int $numSheets
     * @return int
     */
    private function getNumWrittenRowsUsingInlineStrings($resourcePath, $numSheets)
    {
        $numWrittenRowsInLastSheet = 0;
        // to avoid executing the regex of the entire file to get the last row number, we only retrieve the last 10 lines
        $endingSheetXmlContents = $this->getLastTenLinesOfLastSheetFile($resourcePath, $numSheets);

        if (preg_match_all('/<row r="(\d+)"/', $endingSheetXmlContents, $matches)) {
            $lastMatch = array_pop($matches);
            $numWrittenRowsInLastSheet = intval($lastMatch[0]);
        }

        // we are writing the maximum number of rows per worksheet before starting a new worksheet
        $maxRowsPerWorksheet = \ReflectionHelper::getStaticValue('\Box\Spout\Writer\Internal\XLSX\Workbook', 'maxRowsPerWorksheet');

        return (($numSheets - 1) * $maxRowsPerWorksheet + $numWrittenRowsInLastSheet);
    }

    /**
     * @param string $resourcePath
     * @return int
     */
    private function getNumWrittenRowsUsingSharedStrings($resourcePath)
    {
        $numWrittenRowsInSharedStrings = 0;
        // to avoid executing the regex of the entire file to get the last row number, we only retrieve the last 10 lines
        $endingSharedStringsXmlContents = $this->getLastTenLinesOfLastSharedStringsFile($resourcePath);

        if (preg_match_all('/<t.*>xlsx--(\d+)-\d<\/t>/', $endingSharedStringsXmlContents, $matches)) {
            $lastMatch = array_pop($matches);
            $numWrittenRowsInSharedStrings = intval(array_pop($lastMatch));
        }

        return $numWrittenRowsInSharedStrings;
    }

    /**
     * @param string $resourcePath
     * @param int $lastSheetNumber
     * @return string
     */
    private function getLastTenLinesOfLastSheetFile($resourcePath, $lastSheetNumber)
    {
        $pathToLastSheetFile = 'zip://' . $resourcePath . '#xl/worksheets/sheet' . $lastSheetNumber . '.xml';
        return $this->getLastTenLinesOfFile($pathToLastSheetFile);
    }

    /**
     * @param string $resourcePath
     * @param int $lastSheetNumber
     * @return string
     */
    private function getLastTenLinesOfLastSharedStringsFile($resourcePath)
    {
        $pathToSharedStringsFile = 'zip://' . $resourcePath . '#xl/sharedStrings.xml';
        return $this->getLastTenLinesOfFile($pathToSharedStringsFile);
    }

    /**
     * @param string $filePath
     * @return string
     */
    private function getLastTenLinesOfFile($filePath)
    {
        // since we cannot execute "tail" on a file inside a zip, we need to copy it outside first
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'get_last_ten_lines_of_file.xml';
        copy($filePath, $tmpFile);

        $lastTenLines = `tail -n 10 $tmpFile`;

        // remove the temporary file
        unlink($tmpFile);

        return $lastTenLines;
    }
}
