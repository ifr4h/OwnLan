<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\CsvExporter;
use Codeception\Test\Unit;

class CsvExporterTest extends Unit
{
    public function testFormulaInjectionEscaped(): void
    {
        $csv = CsvExporter::build(['name'], [['=HYPERLINK("evil")']]);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function testCommasAndQuotesEscaped(): void
    {
        $csv = CsvExporter::build(['note'], [['Say "hello", world']]);
        $this->assertStringContainsString('"Say ""hello"", world"', $csv);
    }

    public function testPoundsFromPence(): void
    {
        $this->assertSame('42.00', CsvExporter::poundsFromPence(4200));
        $this->assertSame('-1.50', CsvExporter::poundsFromPence(-150));
    }
}
