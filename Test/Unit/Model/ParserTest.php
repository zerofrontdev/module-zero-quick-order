<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\QuickOrder\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Zero\QuickOrder\Model\Parser;

class ParserTest extends TestCase
{
    public function testRowsSkipEmptyOnesAndDefaultTheQuantity(): void
    {
        self::assertSame(
            ['24-MB01' => 2.0, '24-UG06' => 1.0],
            Parser::rows([['sku' => ' 24-MB01 ', 'qty' => '2'], ['sku' => '', 'qty' => '3'], ['sku' => '24-UG06', 'qty' => '0']])
        );
    }

    public function testRowsSkipNestedArraysInsteadOfReadingArray(): void
    {
        $this->assertSame(
            ['24-MB01' => 1.0],
            Parser::rows([['sku' => ['a' => ['b' => '1']], 'qty' => '2'], ['sku' => '24-MB01', 'qty' => ['3']], 'not a row'])
        );
    }

    public function testTextTakesAnySeparatorAndAddsUpDuplicates(): void
    {
        self::assertSame(
            ['24-MB01' => 3.0, '24-UG06' => 1.5, '24-WB02' => 1.0],
            Parser::text("sku,qty\n24-MB01, 2\n24-UG06;1,5\n24-WB02\n# a comment\n\n24-MB01\t1")
        );
    }

    public function testCsvDetectsTheDelimiterAndSkipsTheHeader(): void
    {
        self::assertSame(
            ['24-MB01' => 2.0, '24-UG06' => 1.0],
            Parser::csv("\xEF\xBB\xBFSKU;Qty\n24-MB01;2\n\"24-UG06\";\n")
        );
        self::assertSame([], Parser::csv(''));
    }

    public function testMergeAddsUpAndCapsTheLines(): void
    {
        self::assertSame(['a' => 3.0, 'b' => 1.0], Parser::merge(['a' => 1.0], ['b' => 1.0, 'a' => 2.0]));
        $many = [];
        for ($i = 0; $i < Parser::MAX_LINES + 10; $i++) {
            $many['sku-' . $i] = 1.0;
        }
        self::assertCount(Parser::MAX_LINES, Parser::merge($many));
    }
}
