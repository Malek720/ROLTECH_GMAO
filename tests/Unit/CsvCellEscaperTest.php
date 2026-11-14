<?php

namespace App\Tests\Unit;

use App\Service\CsvCellEscaper;
use PHPUnit\Framework\TestCase;

/**
 * Requirement: CSV formula escaping is shared by every CSV producer (phase 8 "CSV" section,
 * AGENTS.md rule 34). The catalog export must behave exactly as before.
 */
final class CsvCellEscaperTest extends TestCase
{
    public function testEscapePrefixesFormulaLeadingCells(): void
    {
        foreach (['=1+1', '+33600000000', '-5', '@SUM(A1)'] as $value) {
            self::assertSame("'".$value, CsvCellEscaper::escape($value), $value);
        }
    }

    public function testEscapeLeavesPlainCellsUnchanged(): void
    {
        foreach (['', null, '0', 'Module', ' =SUM(A1)', "'quoté", 'Référence à confirmer'] as $value) {
            self::assertSame((string) $value, CsvCellEscaper::escape($value), var_export($value, true));
        }
    }

    public function testUnescapeRemovesOnlyTheFormulaPrefix(): void
    {
        $cases = [
            "'=1+1" => '=1+1',
            "'+33600000000" => '+33600000000',
            "'-5" => '-5',
            "'@SUM(A1)" => '@SUM(A1)',
            "'" => "'",
            "'abc" => "'abc",
            "' x" => "' x",
            'plain' => 'plain',
        ];

        foreach ($cases as $escaped => $expected) {
            self::assertSame($expected, CsvCellEscaper::unescape($escaped), $escaped);
        }
    }

    public function testEscapeThenUnescapeRoundTrips(): void
    {
        foreach (['=FORMULA', '+336', '-1', '@here', 'plain', 'Référence à confirmer', ''] as $value) {
            self::assertSame($value, CsvCellEscaper::unescape(CsvCellEscaper::escape($value)), $value);
        }
    }
}
